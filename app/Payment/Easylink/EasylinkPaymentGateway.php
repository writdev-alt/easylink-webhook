<?php

declare(strict_types=1);

namespace App\Payment\Easylink;

use App\Exceptions\NotifyErrorException;
use App\Payment\Easylink\Enums\PayoutMethod;
use App\Payment\Easylink\Enums\TransferState;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ValueError;
use Wrpay\Core\Enums\TrxStatus;
use Wrpay\Core\Models\Transaction;
use Wrpay\Core\Services\TransactionService;
use Wrpay\Core\Services\WebhookService;
use App\Services\Handlers\WithdrawHandler;

/**
 * Service class for interacting with the Bank Partner API.
 *
 * This class handles all communication with the Bank Partner service, including
 * authentication, signature generation, and API calls for various transactions.
 */
class EasylinkPaymentGateway
{
    /**
     * @var array The Bank Partner API credentials from the configuration.
     */
    protected array $credentials;

    /**
     * @var string The base URL for the Bank Partner API endpoint.
     */
    protected string $baseUrl;

    /**
     * @var string The access token for API authentication.
     */
    protected string $accessToken;

    /**
     * The cache key for storing the Bank Partner access token.
     */
    protected const ACCESS_TOKEN_CACHE_KEY = 'easylink_access_token';

    /**
     * Bank PartnerPaymentGateway constructor.
     *
     * @param  array|null  $customCredentials  Optional custom credentials for specific aggregator
     *
     * @throws RuntimeException If credentials are invalid or inactive.
     */
    public function __construct(?array $customCredentials = null)
    {
        Log::debug('EasylinkPaymentGateway - Constructor called', [
            'has_custom_credentials' => $customCredentials !== null,
            'app_mode' => config('app.mode'),
        ]);

        $this->credentials = $customCredentials ?? config('payment_gateways.easylink.credentials');
        $this->baseUrl = $this->getBaseUrl();

        Log::info('EasylinkPaymentGateway - Initialized', [
            'base_url' => $this->baseUrl,
            'is_sandbox' => $this->isSandbox(),
            'has_credentials' => !empty($this->credentials),
        ]);
    }

    /**
     * Get the appropriate API endpoint based on the application environment.
     */
    protected function getBaseUrl(): string
    {
        $sandboxEndpoint = 'http://sandbox.easylink.id:9080';
        $productionEndpoint = 'https://openapi.easylink.id';
        $appMode = config('app.mode');

        $baseUrl = ($appMode === 'production') ? $productionEndpoint : $sandboxEndpoint;

        Log::debug('EasylinkPaymentGateway - getBaseUrl', [
            'app_mode' => $appMode,
            'selected_endpoint' => $baseUrl,
        ]);

        return $baseUrl;
    }

    /**
     * Check if the current environment is sandbox.
     *
     * @return bool True if sandbox, false otherwise.
     */
    public function isSandbox(): bool
    {
        return $this->baseUrl === 'http://sandbox.easylink.id:9080';
    }

    /**
     * Fetches and caches the access token for API authentication.
     *
     * @throws RuntimeException If authentication fails.
     */
    public function getAccessToken(): void
    {
        Log::info('EasylinkPaymentGateway - getAccessToken - Method started', [
            'base_url' => $this->baseUrl,
            'endpoint' => $this->baseUrl.'/get-access-token',
        ]);

        try {
            $requestBody = [
                'app_id' => $this->credentials['app_id'] ?? null,
                'app_secret' => isset($this->credentials['app_secret']) ? '***masked***' : null,
            ];

            Log::debug('EasylinkPaymentGateway - getAccessToken - Making request', [
                'url' => $this->baseUrl.'/get-access-token',
                'app_id' => $requestBody['app_id'],
                'has_app_secret' => isset($this->credentials['app_secret']),
            ]);

            $response = Http::post($this->baseUrl.'/get-access-token', [
                'app_id' => $this->credentials['app_id'],
                'app_secret' => $this->credentials['app_secret'],
            ]);

            $responseStatus = $response->status();
            $payload = $response->json();
            $token = $payload['data'] ?? null;

            Log::info('EasylinkPaymentGateway - getAccessToken - Response received', [
                'http_status' => $responseStatus,
                'has_data' => isset($payload['data']),
                'response_structure' => array_keys($payload ?? []),
            ]);

            if (is_array($token)) {
                $token = $token['access_token'] ?? $token['token'] ?? reset($token);
                Log::debug('EasylinkPaymentGateway - getAccessToken - Extracted token from array', [
                    'token_keys' => array_keys($token ?? []),
                ]);
            }

            if (! is_string($token) || $token === '') {
                Log::error('EasylinkPaymentGateway - getAccessToken - Invalid token response', [
                    'token_type' => gettype($token),
                    'token_empty' => $token === '',
                    'payload' => $payload,
                ]);
                throw new RuntimeException('Invalid access token response from Easylink API.');
            }

            $this->accessToken = $token;
            Log::info('EasylinkPaymentGateway - getAccessToken - Token retrieved successfully', [
                'token_length' => strlen($this->accessToken),
                'token_prefix' => substr($this->accessToken, 0, 10).'...',
                'full_response' => $payload,
            ]);
        } catch (ConnectionException $e) {
            Log::error('EasylinkPaymentGateway - getAccessToken - Connection exception', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'base_url' => $this->baseUrl,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new RuntimeException('Failed to connect to Easylink API.');
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getAccessToken - General exception', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate headers including the RSA SHA256 signature.
     *
     * @param  array  $body  The request body to be included in the signature.
     * @return array The complete array of headers for the API request.
     *
     * @throws RuntimeException If the signature cannot be generated.
     */
    public function headers(array $body): array
    {
        Log::debug('EasylinkPaymentGateway - headers - Method called', [
            'body_keys' => array_keys($body),
            'has_access_token' => isset($this->accessToken),
        ]);

        $common = [
            'X-EasyLink-AppKey' => $this->credentials['app_key'],
            'X-EasyLink-Nonce' => (string) Str::uuid()->toString(),
            'X-EasyLink-Timestamp' => (string) Carbon::now()->getPreciseTimestamp(3),
        ];

        Log::debug('EasylinkPaymentGateway - headers - Common headers prepared', [
            'app_key' => $this->credentials['app_key'] ?? null,
            'nonce' => $common['X-EasyLink-Nonce'],
            'timestamp' => $common['X-EasyLink-Timestamp'],
        ]);

        try {
            $signature = $this->signature($common, $body);
            Log::debug('EasylinkPaymentGateway - headers - Signature generated', [
                'signature_length' => strlen($signature),
                'signature_prefix' => substr($signature, 0, 20).'...',
            ]);
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - headers - Signature generation failed', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);
            throw $e;
        }

        $headers = [
            'Content-type' => 'application/json',
            'Authorization' => 'Bearer '.$this->accessToken,
            'X-EasyLink-AppKey' => $common['X-EasyLink-AppKey'],
            'X-EasyLink-Nonce' => $common['X-EasyLink-Nonce'],
            'X-EasyLink-Timestamp' => $common['X-EasyLink-Timestamp'],
            'X-EasyLink-Sign' => $signature,
        ];

        Log::debug('EasylinkPaymentGateway - headers - Headers prepared', [
            'header_keys' => array_keys($headers),
            'authorization_prefix' => substr($headers['Authorization'], 0, 20).'...',
            'signature_prefix' => substr($signature, 0, 20).'...',
        ]);

        return $headers;
    }

    /**
     * Generates an RSA SHA256 signature from headers and body.
     *
     * @param  array  $common  Common header parameters.
     * @param  array  $body  The request body.
     * @return string The Base64-encoded signature string.
     *
     * @throws RuntimeException If the private key is invalid or signature generation fails.
     */
    protected function signature(array $common, array $body): string
    {
        Log::debug('EasylinkPaymentGateway - signature - Method called', [
            'common_keys' => array_keys($common),
            'body_keys' => array_keys($body),
        ]);

        $params = array_merge($body, $common);
        ksort($params);

        $originStr = collect($params)
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))
            ->implode('&');

        $signData = $this->credentials['app_key'].$originStr.$this->credentials['app_key'];

        Log::debug('EasylinkPaymentGateway - signature - Sign data prepared', [
            'origin_str_length' => strlen($originStr),
            'sign_data_length' => strlen($signData),
            'has_app_key' => isset($this->credentials['app_key']),
        ]);

        $attemptedPath = 'inline';
        $credentialKey = $this->credentials['private_key'] ?? null;

        if (! empty($credentialKey)) {
            Log::debug('EasylinkPaymentGateway - signature - Using credential key', [
                'key_type' => str_starts_with($credentialKey, '-----BEGIN') ? 'inline_pem' : 'path',
            ]);

            // If credentials provide inline PEM content
            if (str_starts_with($credentialKey, '-----BEGIN')) {
                $privateKeyContent = $credentialKey;
                Log::debug('EasylinkPaymentGateway - signature - Using inline PEM key');
            } else {
                // Treat credential value as a path (support file:// prefix)
                $path = $credentialKey;
                if (str_starts_with($path, 'file://')) {
                    $path = substr($path, 7);
                }
                $attemptedPath = $path;

                Log::debug('EasylinkPaymentGateway - signature - Attempting to load key from path', [
                    'path' => $attemptedPath,
                    'file_exists' => file_exists($path),
                ]);

                if (file_exists($path)) {
                    $privateKeyContent = file_get_contents($path);
                    Log::debug('EasylinkPaymentGateway - signature - Key loaded from file', [
                        'file_size' => strlen($privateKeyContent),
                    ]);
                } else {
                    // Fallback: use the credential string as content
                    $privateKeyContent = $credentialKey;
                    Log::debug('EasylinkPaymentGateway - signature - Using credential as key content (fallback)');
                }
            }
        } else {
            // Resolve key path by app mode with fallback
            $appMode = config('app.mode');
            if ($appMode === 'sandbox') {
                $privateKeyPath = storage_path('app/keys/sandbox/private_key.pem');
            } else {
                $privateKeyPath = storage_path('app/keys/production/private_key.pem');
            }
            $attemptedPath = $privateKeyPath;

            Log::debug('EasylinkPaymentGateway - signature - Resolving key path', [
                'app_mode' => $appMode,
                'primary_path' => $privateKeyPath,
                'primary_exists' => file_exists($privateKeyPath),
            ]);

            if (! file_exists($privateKeyPath)) {
                // Fallback to default keys path
                $fallbackPath = storage_path('app/keys/private_key.pem');
                $attemptedPath = $fallbackPath;
                Log::debug('EasylinkPaymentGateway - signature - Trying fallback path', [
                    'fallback_path' => $fallbackPath,
                    'fallback_exists' => file_exists($fallbackPath),
                ]);

                if (! file_exists($fallbackPath)) {
                    Log::error('EasylinkPaymentGateway - signature - Private key file not found', [
                        'primary_path' => $privateKeyPath,
                        'fallback_path' => $fallbackPath,
                    ]);
                    throw new RuntimeException('Private key file not found at: '.$privateKeyPath.' and fallback: '.$fallbackPath);
                }
                $privateKeyPath = $fallbackPath;
            }

            $privateKeyContent = file_get_contents($privateKeyPath);
            Log::debug('EasylinkPaymentGateway - signature - Key loaded from resolved path', [
                'path' => $privateKeyPath,
                'file_size' => strlen($privateKeyContent),
            ]);
        }

        $passphrase = $this->credentials['private_key_passphrase'] ?? null;
        Log::debug('EasylinkPaymentGateway - signature - Loading private key', [
            'has_passphrase' => $passphrase !== null,
            'key_path' => $attemptedPath,
        ]);

        $privateKey = $passphrase !== null
            ? openssl_pkey_get_private($privateKeyContent, $passphrase)
            : openssl_pkey_get_private($privateKeyContent);

        if (! $privateKey) {
            $error = openssl_error_string();
            Log::error('EasylinkPaymentGateway - signature - Invalid private key', [
                'attempted_path' => $attemptedPath,
                'has_passphrase' => $passphrase !== null,
                'openssl_error' => $error,
            ]);
            throw new RuntimeException('Invalid private key. Attempted: '.$attemptedPath);
        }

        Log::debug('EasylinkPaymentGateway - signature - Signing data', [
            'sign_data_length' => strlen($signData),
            'algorithm' => 'SHA256',
        ]);

        if (! openssl_sign($signData, $signatureBytes, $privateKey, OPENSSL_ALGO_SHA256)) {
            $error = openssl_error_string();
            Log::error('EasylinkPaymentGateway - signature - Failed to generate signature', [
                'openssl_error' => $error,
            ]);
            throw new RuntimeException('Failed to generate signature.');
        }

        $signature = base64_encode($signatureBytes);
        Log::debug('EasylinkPaymentGateway - signature - Signature generated successfully', [
            'signature_length' => strlen($signature),
        ]);

        return $signature;
    }

    /**
     * Get a human-readable label for a given state code.
     *
     * @param  int|string  $stateCode  The state code from the Bank Partner API.
     * @return string The corresponding label.
     */
    /**
     * Get a human-readable label for a given state code.
     *
     * @param  int|string  $stateCode  The state code from the Bank Partner API.
     * @return string|Application|Translator|null The corresponding label.
     */
    protected function getStateLabel(int|string $stateCode): string|Application|Translator|null
    {
        try {
            return TransferState::from((int) $stateCode)->label();
        } catch (\ValueError) {
            return __('Unknown State');
        }
    }

    /**
     * Handle common API HTTP errors and internal Bank Partner errors.
     *
     * @param  RequestException|ConnectionException|Exception  $e  The exception object.
     * @return string The formatted error message.
     */
    protected function handleApiResponseError(mixed $e): string
    {
        if ($e instanceof RequestException) {
            $response = $e->response;

            if ($response->status() === 401) {
                /**
                 * Forgetting the cached access token key
                 */
                Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);

                return 'Failed to verify JWT. Please check your credentials.';
            }

            if ($response->status() === 500) {
                return 'Server error. Please contact admin or customer service.';
            }
        }

        if ($e instanceof ConnectionException) {
            return 'Failed to connect to the API. Please check your network connection and make sure your server IP address has been submitted to whitelisted by Bank Partner.';
        }

        return $e->getMessage();
    }

    /**
     * Maps an internal Bank Partner API error code to a friendly message.
     *
     * @param  int|string  $errorCode  The error code from the API response body.
     * @return string The corresponding friendly error message.
     */
    protected function handleInternalErrorCode(int|string $errorCode): string
    {
        $internalErrors = [
            -1 => 'API service unavailable or internal server error',
            10500003 => 'Invalid Field Format beneficiaryAccountNo',
            10041006 => 'Create transfer exception, please try again later.',
            30010000 => 'Merchant name already exists.',
            30010007 => 'Merchant config not found.',
            30010008 => 'Merchant exists under review fee config.',
            30010009 => 'Merchant fee config does not exist.',
            30010010 => 'Merchant does not exist.',
            30010011 => 'Missing required fields for merchant.',
            30010012 => 'Invalid field value for merchant.',
            30010013 => 'Merchant external ID already exists.',
            30010014 => 'Merchant fee not found.',
            30010015 => 'Error in merchant fee config.',
            30010016 => 'Merchant info not found.',
            30010017 => 'Customer info not found.',
            30010018 => 'Customer information was not authenticated successfully.',
            30010019 => 'The recharge virtual account of the merchant already exists.',
            30010020 => 'Merchant status is abnormal.',
            30020000 => 'Duplicate reference.',
            30020001 => 'Transaction not exist.',
            30020002 => 'Transaction status abnormal.',
            30020003 => 'Transaction amount limit error.',
            30020004 => 'Callback address is not configured.',
            30020005 => 'Transaction amount can not less than 50,000 IDR.',
            40000000 => 'Finance account balance not enough.',
            40001000 => 'Finance account subject already exists.',
            40001001 => 'Finance account subject not found.',
            40002000 => 'Finance balance account already exists.',
            40002001 => 'Finance balance account not found.',
            40002002 => 'Finance balance account is frozen.',
            40002003 => 'Failed to update finance balance account.',
            40002004 => 'Finance balance account balance not enough.',
            581000001 => 'Sign verify error.',
            60001004 => 'This recipient channel is currently not supported',
            581000002 => 'Your IP address is not allowed.',
        ];

        return $internalErrors[(int) $errorCode] ?? 'Unknown internal error: '.$errorCode;
    }

    /**
     * Make an API request to Bank Partner with error handling and JSON caching
     *
     * @param  string  $endpoint  The API endpoint path
     * @param  array  $body  The request body
     * @param  string  $cacheFileName  The JSON file name for caching
     * @return object The response data
     *
     * @throws Exception
     */
    private function makeEasylinkRequest(string $endpoint, array $body, string $cacheFileName): object
    {
        Log::info('EasylinkPaymentGateway - makeEasylinkRequest - Method started', [
            'endpoint' => $endpoint,
            'cache_file' => $cacheFileName,
            'body_keys' => array_keys($body),
        ]);

        $cacheFilePath = storage_path('app/'.$cacheFileName);

        // Check if cache file exists
        if (file_exists($cacheFilePath)) {
            Log::info('EasylinkPaymentGateway - makeEasylinkRequest - Using cached data', [
                'cache_file' => $cacheFilePath,
            ]);
            $cachedData = json_decode(file_get_contents($cacheFilePath));

            return (object) $cachedData->data;
        }

        Log::debug('EasylinkPaymentGateway - makeEasylinkRequest - Cache not found, making API request', [
            'cache_file' => $cacheFilePath,
        ]);

        // File doesn't exist, make API request
        $this->getAccessToken();

        $requestUrl = $this->baseUrl.$endpoint;
        Log::info('EasylinkPaymentGateway - makeEasylinkRequest - Making API request', [
            'url' => $requestUrl,
            'method' => 'POST',
            'body' => $body,
        ]);

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $responseData = $response->object();

            Log::info('EasylinkPaymentGateway - makeEasylinkRequest - API response received', [
                'http_status' => $responseStatus,
                'response_code' => $responseData->code ?? null,
                'has_err_code' => isset($responseData->err_code),
                'has_data' => isset($responseData->data),
                'full_response' => $responseData,
            ]);

            if (isset($responseData->err_code)) {
                $errorMessage = $this->handleInternalErrorCode($responseData->err_code ?? 1);
                Log::error('EasylinkPaymentGateway - makeEasylinkRequest - Error in response', [
                    'err_code' => $responseData->err_code,
                    'error_message' => $errorMessage,
                    'full_response' => $responseData,
                ]);
                throw new Exception($errorMessage);
            } elseif (($responseData->code ?? 1) !== 0) {
                $errorMessage = $this->handleInternalErrorCode($responseData->code ?? 1);
                Log::error('EasylinkPaymentGateway - makeEasylinkRequest - Non-zero response code', [
                    'code' => $responseData->code,
                    'error_message' => $errorMessage,
                    'full_response' => $responseData,
                ]);
                throw new Exception($errorMessage);
            }

            // Cache response data to JSON file
            $directory = dirname($cacheFilePath);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
                Log::debug('EasylinkPaymentGateway - makeEasylinkRequest - Created cache directory', [
                    'directory' => $directory,
                ]);
            }

            file_put_contents($cacheFilePath, json_encode($responseData, JSON_PRETTY_PRINT));
            Log::info('EasylinkPaymentGateway - makeEasylinkRequest - Response cached', [
                'cache_file' => $cacheFilePath,
            ]);

            return (object) $responseData->data;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - makeEasylinkRequest - Exception occurred', [
                'endpoint' => $endpoint,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Lists all supported banks from the Bank Partner API.
     *
     * @param  string  $country  The country code (e.g., 'IDN').
     * @return object The response object from the API.
     *                example:
     *                {
     *                "0": [
     *                "bank_id": "002",
     *                "bank_name": "BCA"
     *                ],
     *                "1": [
     *                "bank_id": "009",
     *                "bank_name": "Mandiri"
     *                ]
     *                }
     *
     * @throws Exception
     */
    public function getBankList(string $country = 'IDN'): object
    {
        Log::info('EasylinkPaymentGateway - getBankList - Method called', [
            'country' => $country,
        ]);

        try {
            $result = $this->makeEasylinkRequest(
                '/v2/data/supported-bank-code',
                ['country' => $country],
                "easylink/bank_list_{$country}.json"
            );

            Log::info('EasylinkPaymentGateway - getBankList - Success', [
                'country' => $country,
                'result_type' => gettype($result),
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getBankList - Failed', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Lists all supported branches from the Bank Partner API.
     *
     * @param  string  $country  The country code (e.g., 'IDN').
     * @param  string  $bankCode  The bank code (e.g., '002').
     * @return object The response object from the API.
     */
    public function getBranchList(string $country = 'IDN', string $bankCode = '002'): object
    {
        Log::info('EasylinkPaymentGateway - getBranchList - Method called', [
            'country' => $country,
            'bank_code' => $bankCode,
        ]);

        try {
            $result = $this->makeEasylinkRequest(
                '/data/branch-list',
                ['country' => $country, 'bank_code' => $bankCode],
                "easylink/branch_list_{$country}_{$bankCode}.json"
            );

            Log::info('EasylinkPaymentGateway - getBranchList - Success', [
                'country' => $country,
                'bank_code' => $bankCode,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getBranchList - Failed', [
                'country' => $country,
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Lists all supported wallets from the Bank Partner API.
     *
     * @throws Exception
     */
    public function getWalletList(): object
    {
        Log::info('EasylinkPaymentGateway - getWalletList - Method called');

        try {
            $result = $this->makeEasylinkRequest(
                '/v2/data/supported-inst-code',
                [],
                'easylink/wallet_list.json'
            );

            Log::info('EasylinkPaymentGateway - getWalletList - Success');

            return $result;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getWalletList - Failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Lists all supported Virtual Accounts from the Bank Partner API.
     *
     * @throws Exception
     */
    public function getVaAccountList(): object
    {
        Log::info('EasylinkPaymentGateway - getVaAccountList - Method called');

        try {
            $result = $this->makeEasylinkRequest(
                '/virtual-account/get-available-virtual-account-banks',
                [],
                'easylink/va_account_list.json'
            );

            Log::info('EasylinkPaymentGateway - getVaAccountList - Success');

            return $result;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getVaAccountList - Failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verifies a bank account via the Bank Partner API.
     *
     * @param  string  $bankId  The ID of the bank.
     * @param  string  $accountNumber  The account number to verify.
     * @param  string  $paymentMethod  The payment method type.
     * @return bool Returns false if invalid bank account number
     *
     * @throws Exception
     */
    public function verifyBankAccount(string $bankId, string $accountNumber, string $paymentMethod): bool
    {
        Log::info('Easylink verifyBankAccount - Method started', [
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'payment_method' => $paymentMethod,
            'base_url' => $this->baseUrl,
            'is_sandbox' => $this->isSandbox(),
        ]);

        $body = [
            'account_number' => $accountNumber,
            'bank_id' => $bankId,
            'payment_method' => $paymentMethod,
        ];

        Log::debug('Easylink verifyBankAccount - Request body prepared', [
            'body' => $body,
        ]);

        try {
            Log::debug('Easylink verifyBankAccount - Getting access token');
            $this->getAccessToken();
            Log::debug('Easylink verifyBankAccount - Access token retrieved', [
                'token_length' => strlen($this->accessToken),
                'token_prefix' => substr($this->accessToken, 0, 10).'...',
            ]);

            $headers = $this->headers($body);
            $requestUrl = $this->baseUrl.'/v2/transfer/verify-bank-account';

            Log::info('Easylink verifyBankAccount - Making API request', [
                'url' => $requestUrl,
                'method' => 'POST',
                'timeout' => 60,
                'headers' => [
                    'Content-type' => $headers['Content-type'] ?? null,
                    'Authorization' => isset($headers['Authorization']) ? substr($headers['Authorization'], 0, 20).'...' : null,
                    'X-EasyLink-AppKey' => $headers['X-EasyLink-AppKey'] ?? null,
                    'X-EasyLink-Nonce' => $headers['X-EasyLink-Nonce'] ?? null,
                    'X-EasyLink-Timestamp' => $headers['X-EasyLink-Timestamp'] ?? null,
                    'X-EasyLink-Sign' => isset($headers['X-EasyLink-Sign']) ? substr($headers['X-EasyLink-Sign'], 0, 20).'...' : null,
                ],
                'body' => $body,
            ]);

            $response = Http::timeout(60)->withHeaders($headers)
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $responseHeaders = $response->headers();
            $responseBody = $response->body();
            $responseData = $response->object();

            Log::info('Easylink verifyBankAccount - API response received', [
                'http_status' => $responseStatus,
                'response_headers' => $responseHeaders,
                'response_body_raw' => $responseBody,
                'response_data' => $responseData,
            ]);

            if ($responseData->code === 0) {
                Log::info('Easylink verifyBankAccount - Bank account verification successful', [
                    'bank_id' => $bankId,
                    'account_number' => $accountNumber,
                    'payment_method' => $paymentMethod,
                    'response_code' => $responseData->code,
                ]);

                return true;
            } else {
                $errorMessage = $this->handleInternalErrorCode($responseData->code);
                Log::error('Easylink verifyBankAccount - Bank account verification failed', [
                    'bank_id' => $bankId,
                    'account_number' => $accountNumber,
                    'payment_method' => $paymentMethod,
                    'response_code' => $responseData->code,
                    'error_message' => $errorMessage,
                    'full_response' => $responseData,
                ]);

                return false;
            }
        } catch (ConnectionException $e) {
            Log::error('Easylink verifyBankAccount - Connection exception', [
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'payment_method' => $paymentMethod,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception($this->handleApiResponseError($e));
        } catch (RequestException $e) {
            $response = $e->response;
            Log::error('Easylink verifyBankAccount - Request exception', [
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'payment_method' => $paymentMethod,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'http_status' => $response ? $response->status() : null,
                'response_body' => $response ? $response->body() : null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception($this->handleApiResponseError($e));
        } catch (Exception $e) {
            Log::error('Easylink verifyBankAccount - General exception', [
                'bank_id' => $bankId,
                'account_number' => $accountNumber,
                'payment_method' => $paymentMethod,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception($this->handleApiResponseError($e));
        }
    }

    /**
     * Lists all remittance transactions from the Bank Partner API.
     *
     * @param string $startDate The start date of the remittance transactions.
     * @param string $endDate The end date of the remittance transactions.
     * @param string|null $pageSize The page size of the remittance transactions.
     * @param string|null $pageNumber The page number of the remittance transactions.
     * @return object|bool The response object from the API.
     * @throws Exception
     */
    public function getRemittanceList(string $startDate, string $endDate, ?string $pageSize = null, ?string $pageNumber = null): object|bool
    {
        Log::info('EasylinkPaymentGateway - getRemittanceList - Method started', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'page_size' => $pageSize,
            'page_number' => $pageNumber,
        ]);

        $body = [
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'page_size' => $pageSize ?? 10,
            'page_number' => $pageNumber ?? 1,
        ];

        $this->getAccessToken();

        try {
            $requestUrl = $this->baseUrl.'/transfer/get-remittance-list';
            Log::info('EasylinkPaymentGateway - getRemittanceList - Making API request', [
                'url' => $requestUrl,
                'body' => $body,
            ]);

            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $response->throw();

            $responseData = $response->object();
            Log::info('EasylinkPaymentGateway - getRemittanceList - Response received', [
                'http_status' => $responseStatus,
                'response_code' => $responseData->code ?? null,
                'has_err_code' => isset($responseData->err_code),
                'full_response' => $responseData,
            ]);

            if (isset($responseData->err_code)) {
                Log::error('EasylinkPaymentGateway - getRemittanceList - Error code in response', [
                    'err_code' => $responseData->err_code,
                    'message' => $responseData->message ?? null,
                ]);
                throw new Exception($responseData->message);
            }

            if (($responseData->code ?? 1) === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;

                Log::info('EasylinkPaymentGateway - getRemittanceList - Success', [
                    'state' => $responseData->data->state ?? null,
                    'state_label' => $stateLabel,
                ]);

                return $responseData->data;
            } elseif ($errorMessage = $this->handleInternalErrorCode($responseData->code)) {
                Log::error('EasylinkPaymentGateway - getRemittanceList - Error', [
                    'response_code' => $responseData->code,
                    'error_message' => $errorMessage,
                ]);

                if ($errorMessage === 'Duplicate reference.') {
                    Log::warning('EasylinkPaymentGateway - getRemittanceList - Duplicate reference detected');

                    if (isset($transaction->trx_data['easylink_disbursement'])) {
                        return (object) $transaction->trx_data['easylink_disbursement'];
                    }

                    return (object) [
                        'state' => TransferState::CREATE,
                        'message' => 'Duplicate Reference',
                    ];
                }

                return (object) [
                    'state' => TransferState::FAILED,
                    'message' => $errorMessage,
                ];
            } else {
                $errorMessage = 'Unknown internal error: '.$responseData->code.' with response message: '.$responseData->message;
                Log::error('EasylinkPaymentGateway - getRemittanceList - Unknown error', [
                    'response_code' => $responseData->code,
                    'response_message' => $responseData->message ?? null,
                    'error_message' => $errorMessage,
                ]);

                return (object) [
                    'state' => TransferState::FAILED,
                    'message' => $errorMessage,
                ];
            }
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getRemittanceList - Exception', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception($this->handleApiResponseError($e));
        }
    }

    /**
     * Initiates a new domestic transfer via the Bank Partner API.
     *
     * @param  Transaction  $transaction  The transaction model for the transfer.
     * @return object|bool The response object from the API.
     *
     * @throws Exception
     */
    public function createDomesticTransfer(Transaction $transaction): object|bool
    {
        Log::info('EasylinkPaymentGateway - createDomesticTransfer - Method started', [
            'transaction_id' => $transaction->trx_id,
            'provider' => $transaction->provider,
            'net_amount' => $transaction->net_amount,
            'is_sandbox' => $this->isSandbox(),
        ]);

        $withdrawalAccount = $transaction->trx_data['withdrawal_account'] ?? null;

        if (! $withdrawalAccount) {
            Log::error('EasylinkPaymentGateway - createDomesticTransfer - Missing withdrawal account data', [
                'transaction_id' => $transaction->trx_id,
            ]);
            throw new InvalidArgumentException('Missing withdrawal account data in transaction.');
        }

        $bankId = $withdrawalAccount['account_bank_code'] ?? null;
        $accNo = $withdrawalAccount['account_number'] ?? null;
        $accName = $withdrawalAccount['account_holder_name'] ?? null;

        Log::debug('EasylinkPaymentGateway - createDomesticTransfer - Account details extracted', [
            'bank_id' => $bankId,
            'account_number' => $accNo,
            'account_holder_name' => $accName,
        ]);

        if ($this->isSandbox()) {
            // In sandbox mode, override the account details with test values
            $bankId = '2'; // Example bank code for MANDIRI
            $accNo = '8730700000000001'; // Example account number
            $accName = 'Andohar Erwin Juniarta'; // Example account holder name
            Log::info('EasylinkPaymentGateway - createDomesticTransfer - Using sandbox test account', [
                'bank_id' => $bankId,
                'account_number' => $accNo,
            ]);
        }

        if (! $bankId || ! $accNo || ! $accName) {
            Log::error('EasylinkPaymentGateway - createDomesticTransfer - Missing required account data', [
                'has_bank_id' => !empty($bankId),
                'has_account_number' => !empty($accNo),
                'has_account_name' => !empty($accName),
            ]);
            throw new InvalidArgumentException('Missing required transaction data for Bank Partner transfer.');
        }

        $payoutMethodCode = PayoutMethod::fromProviderName($transaction->provider);
        if ($payoutMethodCode === 0) {
            Log::error('EasylinkPaymentGateway - createDomesticTransfer - Unsupported payout method', [
                'provider' => $transaction->provider,
            ]);
            throw new InvalidArgumentException('Unsupported Payout Method Code.');
        }

        $reference = $transaction->trx_id;

        $body = [
            'reference' => $reference,
            'bank_id' => (string) $bankId,
            'account_holder_name' => $accName,
            'account_number' => $accNo,
            'amount' => (string) $transaction->net_amount,
            'payment_method' => (string) $payoutMethodCode,
        ];

        Log::info('EasylinkPaymentGateway - createDomesticTransfer - Request body prepared', [
            'body' => $body,
        ]);

        $this->getAccessToken();

        $headers = $this->headers($body);
        Log::info('EasylinkPaymentGateway - createDomesticTransfer - Headers prepared', [
            'header_keys' => array_keys($headers),
            'base_url' => $this->baseUrl,
        ]);

        try {
            $requestUrl = $this->baseUrl.'/v2/transfer/create-domestic-transfer';
            Log::info('EasylinkPaymentGateway - createDomesticTransfer - Making API request', [
                'url' => $requestUrl,
                'transaction_id' => $transaction->trx_id,
            ]);

            $response = Http::timeout(60)->withHeaders($headers)
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $response->throw();

            $responseData = $response->object();

            Log::info('EasylinkPaymentGateway - createDomesticTransfer - Response received', [
                'http_status' => $responseStatus,
                'response_code' => $responseData->code ?? null,
                'has_err_code' => isset($responseData->err_code),
                'has_data' => isset($responseData->data),
                'full_response' => $responseData,
            ]);

            if (isset($responseData->err_code)) {
                Log::error('EasylinkPaymentGateway - createDomesticTransfer - Error code in response', [
                    'err_code' => $responseData->err_code,
                    'message' => $responseData->message ?? null,
                    'transaction_id' => $transaction->trx_id,
                ]);
                throw new Exception($responseData->message);
            }

            if (($responseData->code ?? 1) === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;

                Log::info('EasylinkPaymentGateway - createDomesticTransfer - Transfer created successfully', [
                    'transaction_id' => $transaction->trx_id,
                    'state' => $responseData->data->state ?? null,
                    'state_label' => $stateLabel,
                ]);

                return $responseData->data;
            } elseif ($errorMessage = $this->handleInternalErrorCode($responseData->code)) {
                Log::error('EasylinkPaymentGateway - createDomesticTransfer - Error response', [
                    'response_code' => $responseData->code,
                    'error_message' => $errorMessage,
                    'transaction_id' => $transaction->trx_id,
                ]);

                if ($errorMessage === 'Duplicate reference.') {
                    Log::warning('EasylinkPaymentGateway - createDomesticTransfer - Duplicate reference', [
                        'transaction_id' => $transaction->trx_id,
                    ]);

                    if (isset($transaction->trx_data['easylink_disbursement'])) {
                        return (object) $transaction->trx_data['easylink_disbursement'];
                    }

                    return (object) [
                        'state' => TransferState::CREATE,
                        'message' => 'Duplicate Reference',
                    ];
                }

                return (object) [
                    'state' => TransferState::FAILED,
                    'message' => $errorMessage,
                ];
            } else {
                $errorMessage = 'Unknown internal error: '.$responseData->code.' with response message: '.$responseData->message;
                Log::error('EasylinkPaymentGateway - createDomesticTransfer - Unknown error', [
                    'response_code' => $responseData->code,
                    'response_message' => $responseData->message ?? null,
                    'error_message' => $errorMessage,
                    'transaction_id' => $transaction->trx_id,
                ]);

                return (object) [
                    'state' => TransferState::FAILED,
                    'message' => $errorMessage,
                ];
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'Duplicate reference.') {
                Log::warning('EasylinkPaymentGateway - createDomesticTransfer - Duplicate reference exception', [
                    'transaction_id' => $transaction->trx_id,
                    'error' => $e->getMessage(),
                ]);

                if (isset($transaction->trx_data['easylink_disbursement'])) {
                    return (object) $transaction->trx_data['easylink_disbursement'];
                }

                return (object) [
                    'state' => TransferState::CREATE,
                    'message' => 'Duplicate Reference',
                ];
            }

            Log::error('EasylinkPaymentGateway - createDomesticTransfer - Exception', [
                'transaction_id' => $transaction->trx_id,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception($this->handleApiResponseError($e));
        }
    }

    /**
     * Get a domestic transfer status by its reference ID.
     *
     * @param  string  $referenceId  The reference ID of the domestic transfer.
     * @return object The response object from the API.
     */
    public function getDomesticTransferUpdate(string $referenceId): object
    {
        Log::info('EasylinkPaymentGateway - getDomesticTransferUpdate - Method started', [
            'reference_id' => $referenceId,
        ]);

        $body = [
            'reference' => $referenceId,
        ];
        $this->getAccessToken();

        try {
            $requestUrl = $this->baseUrl.'/transfer/get-domestic-transfer';
            Log::info('EasylinkPaymentGateway - getDomesticTransferUpdate - Making API request', [
                'url' => $requestUrl,
                'reference_id' => $referenceId,
            ]);

            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $response->throw();

            $responseData = $response->object();
            Log::info('EasylinkPaymentGateway - getDomesticTransferUpdate - Response received', [
                'http_status' => $responseStatus,
                'response_code' => $responseData->code ?? null,
                'reference_id' => $referenceId,
                'full_response' => $responseData,
            ]);

            if ($responseData->code === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;

                Log::info('EasylinkPaymentGateway - getDomesticTransferUpdate - Success', [
                    'reference_id' => $referenceId,
                    'state' => $responseData->data->state ?? null,
                    'state_label' => $stateLabel,
                ]);
            } else {
                $errorMessage = $this->handleInternalErrorCode($responseData->code);

                Log::error('EasylinkPaymentGateway - getDomesticTransferUpdate - Error response', [
                    'reference_id' => $referenceId,
                    'response_code' => $responseData->code,
                    'error_message' => $errorMessage,
                ]);

                return (object) [
                    'state' => TransferState::FAILED,
                    'message' => $errorMessage,
                ];
            }

            return $responseData;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getDomesticTransferUpdate - Exception', [
                'reference_id' => $referenceId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'state' => TransferState::FAILED,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifies the status of a domestic transfer based on a transaction model.
     *
     * This method fetches the latest status from the API using the transaction's reference ID.
     *
     * @param  Transaction  $transaction  The transaction model.
     * @return bool Returns false if failed.
     *
     * @throws Exception
     */
    public function verifyDomesticTransfer(Transaction $transaction): bool
    {
        Log::info('EasylinkPaymentGateway - verifyDomesticTransfer - Method started', [
            'transaction_id' => $transaction->trx_id,
            'transaction_status' => $transaction->status->value ?? null,
        ]);

        $settlementData = $this->getDomesticTransferUpdate($transaction->trx_id);

        $payload = $settlementData->data ?? null;

        Log::debug('EasylinkPaymentGateway - verifyDomesticTransfer - Settlement data received', [
            'transaction_id' => $transaction->trx_id,
            'has_payload' => $payload !== null,
            'payload_empty' => empty((array) $payload),
        ]);

        if (empty((array) $payload)) {
            Log::error('EasylinkPaymentGateway - verifyDomesticTransfer - Payload empty', [
                'settlementData' => $settlementData,
                'transactionId' => $transaction->trx_id,
            ]);

            $remarks = 'Withdrawal request is failed on Financial Institution: '.($settlementData->data->message ?? '');
            $description = 'Withdrawal request is failed on Bank Partner: '.($settlementData->data->message ?? '');

            Log::info('EasylinkPaymentGateway - verifyDomesticTransfer - Refunding transaction', [
                'transaction_id' => $transaction->trx_id,
                'remarks' => $remarks,
            ]);

            app(TransactionService::class)->refundTransaction($transaction->trx_id, $remarks, $description);

            Log::info('EasylinkPaymentGateway - verifyDomesticTransfer - Sending webhook', [
                'transaction_id' => $transaction->trx_id,
            ]);

            return app(WebhookService::class)->sendWithdrawalWebhook($transaction, $description);
        }

        $data = [
            'disbursement_id' => $payload->disbursement_id,
            'reference_id' => $payload->reference,
            'remittance_type' => $payload->remittance_type,
            'source_country' => $payload->source_country,
            'source_currency' => $payload->source_currency,
            'destination_country' => $payload->destination_country,
            'destination_currency' => $payload->destination_currency,
            'source_amount' => $payload->source_amount,
            'destination_amount' => $payload->destination_amount,
            'fee' => $payload->fee,
            'state' => $payload->state,
            'state_change_time' => $payload->created_at,
            'reason' => $payload->reason,
        ];

        Log::info('EasylinkPaymentGateway - verifyDomesticTransfer - Updating settlement data', [
            'transaction_id' => $transaction->trx_id,
            'state' => $data['state'] ?? null,
            'disbursement_id' => $data['disbursement_id'] ?? null,
        ]);

        return $this->updateSettlementTransactionData($transaction, $data);
    }

    /**
     * Retrieves the finance account balance from the Bank Partner API.
     *
     * @return object The response object containing the account balance data.
     *
     * @throws Exception
     */
    public function getAccountBalances(): object
    {
        Log::info('EasylinkPaymentGateway - getAccountBalances - Method started');

        $this->getAccessToken();
        $body = [];

        try {
            $requestUrl = $this->baseUrl.'/finance-account/balances';
            Log::info('EasylinkPaymentGateway - getAccountBalances - Making API request', [
                'url' => $requestUrl,
            ]);

            $response = Http::timeout(60)
                ->withHeaders($this->headers($body))
                ->post($requestUrl, $body);

            $responseStatus = $response->status();
            $responseBody = $response->body();

            Log::info('EasylinkPaymentGateway - getAccountBalances - Response received', [
                'http_status' => $responseStatus,
                'response_body' => $responseBody,
            ]);

            $response->throw();

            $responseData = $response->object();

            Log::info('EasylinkPaymentGateway - getAccountBalances - Response parsed', [
                'response_code' => $responseData->code ?? null,
                'has_data' => isset($responseData->data),
            ]);

            if (($responseData->code ?? 1) !== 0) {
                $errorMessage = $this->handleInternalErrorCode($responseData->code ?? 1);
                Log::error('EasylinkPaymentGateway - getAccountBalances - Error response', [
                    'response_code' => $responseData->code,
                    'error_message' => $errorMessage,
                ]);
                throw new Exception($errorMessage);
            } elseif (isset($responseData->data)) {
                Log::info('EasylinkPaymentGateway - getAccountBalances - Success');
                return (object) $responseData->data;
            }

            Log::info('EasylinkPaymentGateway - getAccountBalances - Success (fallback)');
            return (object) $responseData->data;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - getAccountBalances - Exception', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception($this->handleApiResponseError($e));
        }
    }

    /**
     * Validates the webhook data from the Bank Partner API.
     *
     * @param  array  $data  The webhook data from the Bank Partner API.
     * @return bool Whether the webhook data was validated successfully.
     */
    public function validateWebHook(array $data): bool
    {
        Log::info('EasylinkPaymentGateway - validateWebHook - Method started', [
            'reference_id' => $data['reference_id'] ?? null,
            'disbursement_id' => $data['disbursement_id'] ?? null,
            'state' => $data['state'] ?? null,
        ]);

        $this->getAccessToken();
        $validationData = [
            'disbursement_id' => $data['disbursement_id'],
            'reference_id' => $data['reference_id'],
            'remittance_type' => $data['remittance_type'],
            'source_country' => $data['source_country'],
            'source_currency' => $data['source_currency'],
            'destination_country' => $data['destination_country'],
            'destination_currency' => $data['destination_currency'],
            'source_amount' => $data['source_amount'],
            'destination_amount' => $data['destination_amount'],
            'fee' => $data['fee'],
            'state' => $data['state'],
            'state_change_time' => $data['state_change_time'],
            'reason' => $data['reason'],
        ];

        Log::debug('EasylinkPaymentGateway - validateWebHook - Validation data prepared', [
            'data_keys' => array_keys($validationData),
        ]);

        try {
            $requestUrl = $this->baseUrl;
            Log::info('EasylinkPaymentGateway - validateWebHook - Making validation request', [
                'url' => $requestUrl,
                'reference_id' => $validationData['reference_id'] ?? null,
            ]);

            $response = Http::timeout(60)->withHeaders($this->headers($validationData))
                ->post($requestUrl, $validationData);

            $responseStatus = $response->status();
            $responseData = $response->object();

            Log::info('EasylinkPaymentGateway - validateWebHook - Response received', [
                'http_status' => $responseStatus,
                'response_status' => $response->status ?? null,
                'full_response' => $responseData,
            ]);

            if (isset($response->status) && $response->status === 'success') {
                Log::info('EasylinkPaymentGateway - validateWebHook - Validation successful', [
                    'reference_id' => $validationData['reference_id'] ?? null,
                ]);
                return true;
            }

            Log::warning('EasylinkPaymentGateway - validateWebHook - Validation failed', [
                'reference_id' => $validationData['reference_id'] ?? null,
                'response_status' => $response->status ?? null,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('EasylinkPaymentGateway - validateWebHook - Exception', [
                'reference_id' => $validationData['reference_id'] ?? null,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception($this->handleApiResponseError($e));
        }
    }

    /**
     * Updates the transaction data with the settlement data from the Bank Partner API.
     *
     * @param  Transaction  $transaction  The transaction model.
     * @param  array  $settlementData  The settlement data from the Bank Partner API.
     * @return bool Whether the settlement data was updated successfully.
     */
    public function updateSettlementTransactionData(Transaction $transaction, array $settlementData): bool
    {
        Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - Method started', [
            'transaction_id' => $transaction->trx_id,
            'reference_id' => $settlementData['reference_id'] ?? null,
            'disbursement_id' => $settlementData['disbursement_id'] ?? null,
            'raw_state' => $settlementData['state'] ?? null,
        ]);

        $data = array_merge($transaction->trx_data ?? [], [
            'easylink_settlement' => $settlementData ?? [],
        ]);
        $transaction->update([
            'trx_data' => $data,
        ]);

        Log::debug('EasylinkPaymentGateway - updateSettlementTransactionData - Transaction data updated', [
            'transaction_id' => $transaction->trx_id,
        ]);

        $rawState = $settlementData['state'] ?? null;

        try {
            $stateEnum = match (true) {
                $rawState instanceof TransferState => $rawState,
                is_int($rawState) => TransferState::fromStatusCode($rawState),
                is_string($rawState) && is_numeric($rawState) => TransferState::fromStatusCode((int) $rawState),
                default => null,
            };

            Log::debug('EasylinkPaymentGateway - updateSettlementTransactionData - State enum resolved', [
                'transaction_id' => $transaction->trx_id,
                'raw_state' => $rawState,
                'state_enum' => $stateEnum?->name ?? null,
                'state_value' => $stateEnum?->value ?? null,
            ]);
        } catch (ValueError $exception) {
            $stateEnum = null;
            Log::warning('EasylinkPaymentGateway - updateSettlementTransactionData - ValueError resolving state', [
                'transaction_id' => $transaction->trx_id,
                'raw_state' => $rawState,
                'error' => $exception->getMessage(),
            ]);
        }

        if (! $stateEnum instanceof TransferState) {
            Log::error('EasylinkPaymentGateway - updateSettlementTransactionData - Unknown transfer state', [
                'transaction_id' => $transaction->trx_id,
                'reference_id' => $settlementData['reference_id'] ?? null,
                'raw_state' => var_export($settlementData['state'], true),
                'state_type' => gettype($settlementData['state'] ?? null),
            ]);
            throw new \Exception(
                'Unknown Transfer State: '.var_export($settlementData['state'], true).
                ' for Transaction ID: '.$settlementData['reference_id']
            );
        }

        Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - Processing state', [
            'transaction_id' => $transaction->trx_id,
            'state' => $stateEnum->name,
            'state_value' => $stateEnum->value,
        ]);

        /**
         * Map the internal TransferState (Easylink) to the external TrxStatus and remarks.
         * Each handled state returns a boolean indicating whether the notification
         * was processed successfully.
         */
        switch ($stateEnum) {
            case TransferState::CREATE: // 1
                $remarks = 'Withdrawal request is created by Financial Institution';
                Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - State: CREATE', [
                    'transaction_id' => $transaction->trx_id,
                ]);

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                );

            case TransferState::CONFIRM: // 2
                $remarks = 'Withdrawal request is confirmed by Financial Institution';
                $description = 'Withdrawal request is confirmed by Bank Partner';

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::HOLD: // 3
                $remarks = 'Withdrawal request is hold by Financial Institution';
                $description = 'Withdrawal request is hold by Bank Partner';

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::REVIEW: // 4
                $remarks = 'Withdrawal request is under review by Financial Institution';
                $description = 'Withdrawal request is under review by Bank Partner';

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::PAYOUT: // 5
                $remarks = 'Withdrawal request is being processed in Payout Queue by Financial Institution';
                $description = 'Withdrawal request is being processed in Payout Queue by Bank Partner';

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::SENT: // 6
                $remarks = 'Withdrawal request is sent by Financial Institution';
                $description = 'Withdrawal request is sent by Bank Partner';

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::CANCELED: // 8
                $remarks = 'Withdrawal request has been cancelled by Financial Institution';
                $description = 'Withdrawal request has been cancelled by Bank Partner';

                Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - State: CANCELED', [
                    'transaction_id' => $transaction->trx_id,
                ]);

                return app(TransactionService::class)->failTransaction(
                    trxId: $settlementData['reference_id'],
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::FAILED: // 9
                $remarks = 'Withdrawal request is failed on Financial Institution: '.($settlementData['message'] ?? '');
                $description = 'Withdrawal request is failed on Bank Partner: '.($settlementData['message'] ?? '');

                Log::error('EasylinkPaymentGateway - updateSettlementTransactionData - State: FAILED', [
                    'transaction_id' => $transaction->trx_id,
                    'message' => $settlementData['message'] ?? null,
                ]);

                return app(TransactionService::class)->refundTransaction(
                    trxId: $settlementData['reference_id'],
                    referenceNumber: $settlementData['disbursement_id'],
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::REFUND_SUCCESS: // 10
                $remarks = 'Withdrawal request has been cancelled and refunded by Financial Institution';
                $description = 'Withdrawal request has been cancelled and refunded by Bank Partner';

                Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - State: REFUND_SUCCESS', [
                    'transaction_id' => $transaction->trx_id,
                    'disbursement_id' => $settlementData['disbursement_id'] ?? null,
                ]);

                return app(TransactionService::class)->refundTransaction(
                    trxId: $settlementData['reference_id'],
                    referenceNumber: $settlementData['disbursement_id'],
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::PROCESSING_BANK_PARTNER: // 26
                $remarks = 'Withdrawal request is processing by Financial Institution Bank Partner';
                $description = 'Withdrawal request is processing by Bank Partner';
                app(WebhookService::class)->sendWithdrawalWebhook($transaction, $remarks);

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::AWAITING_FI_PROCESS,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::COMPLETE: // 7
            case TransferState::REMIND_RECIPIENT: // 27 (treated as success for international remittance)
                $isReminder = $stateEnum === TransferState::REMIND_RECIPIENT;
                $remarks = $isReminder
                    ? 'Withdrawal request completed (international remittance reminder)'
                    : 'Withdrawal request is completed by Financial Institution';
                $description = $isReminder
                    ? 'Withdrawal request completed (international remittance reminder)'
                    : 'Withdrawal request is completed by Bank Partner';

                $referenceNumber = $settlementData['disbursement_id'] ?? $settlementData['reference_id'];

                Log::info('EasylinkPaymentGateway - updateSettlementTransactionData - State: COMPLETE/REMIND_RECIPIENT', [
                    'transaction_id' => $transaction->trx_id,
                    'is_reminder' => $isReminder,
                    'reference_number' => $referenceNumber,
                ]);

                app(WithdrawHandler::class)->handleSuccess($transaction);

                return app(TransactionService::class)->completeTransaction(
                    trxId: $settlementData['reference_id'],
                    referenceNumber: $referenceNumber,
                    remarks: $remarks,
                    description: $description
                );

            default:
                $remarks = 'Withdrawal request is in '.$stateEnum->label().' state by Financial Institution';
                $description = 'Withdrawal request is in '.$stateEnum->label().' state by Bank Partner';

                Log::warning('EasylinkPaymentGateway - updateSettlementTransactionData - Unknown/default state', [
                    'transaction_id' => $transaction->trx_id,
                    'state' => $stateEnum->name,
                    'state_value' => $stateEnum->value,
                    'state_label' => $stateEnum->label(),
                ]);

                app(WebhookService::class)->sendWithdrawalWebhook($transaction, $remarks);

                return app(TransactionService::class)->updateTransactionStatusWithRemarks(
                    transaction: $transaction,
                    status: TrxStatus::FAILED,
                    remarks: $remarks,
                    description: $description
                );

        }
    }

    /**
     * Handles the disbursement response from the Bank Partner API.
     *
     * @param  Request  $request  The request object.
     * @return bool Whether the disbursement notification was handled.
     *
     * @throws NotifyErrorException
     * @throws \Throwable
     */
    public function handleDisbursment(Request $request): bool
    {
        $settlementData = $request->toArray();
        Log::info('EasylinkPaymentGateway - handleDisbursment - Method started', [
            'reference_id' => $settlementData['reference_id'] ?? null,
            'disbursement_id' => $settlementData['disbursement_id'] ?? null,
            'state' => $settlementData['state'] ?? null,
            'full_data' => $settlementData,
        ]);

        // if (app()->inProduction()) {
        //     if (! $this->validateWebHook($settlementData)) {
        //         Log::error('EasylinkPaymentGateway - handleDisbursment - Webhook validation failed', [
        //             'reference_id' => $settlementData['reference_id'] ?? null,
        //             'settlement_data' => $settlementData,
        //         ]);
        //         throw new NotifyErrorException('Easylink Webhook validation failed');
        //     }
        // }

        $referenceId = $request->reference_id ?? null;
        if (! $referenceId) {
            Log::error('EasylinkPaymentGateway - handleDisbursment - Missing reference_id', [
                'request_data' => $settlementData,
            ]);
            return false;
        }

        $transaction = Transaction::where('trx_id', $referenceId)->first();

        if (! $transaction) {
            Log::warning('EasylinkPaymentGateway - handleDisbursment - Transaction not found', [
                'reference_id' => $referenceId,
            ]);
            return false;
        }

        Log::info('EasylinkPaymentGateway - handleDisbursment - Transaction found', [
            'reference_id' => $referenceId,
            'transaction_id' => $transaction->id ?? null,
            'current_status' => $transaction->status->value ?? null,
        ]);

        return $this->updateSettlementTransactionData($transaction, $settlementData);
    }

    /**
     * Handles the topup response from the Bank Partner API.
     *
     * @param  Request  $request  The request object.
     * @return bool Whether the topup notification was handled.
     *
     * @throws NotifyErrorException
     * @throws \Throwable
     */
    public function handleTopup(Request $request): bool
    {
        $requestData = $request->toArray();
        Log::info('EasylinkPaymentGateway - handleTopup - Method started', [
            'reference_id' => $requestData['reference_id'] ?? null,
            'disbursement_id' => $requestData['disbursement_id'] ?? null,
            'state' => $requestData['state'] ?? null,
            'full_data' => $requestData,
        ]);

        $referenceId = $request->reference_id ?? null;
        if (! $referenceId) {
            Log::error('EasylinkPaymentGateway - handleTopup - Missing reference_id', [
                'request_data' => $requestData,
            ]);
            return false;
        }

        $transaction = Transaction::where('trx_id', $referenceId)->first();

        if (! $transaction) {
            Log::warning('EasylinkPaymentGateway - handleTopup - Transaction not found', [
                'reference_id' => $referenceId,
            ]);
            return false;
        }

        Log::info('EasylinkPaymentGateway - handleTopup - Transaction found', [
            'reference_id' => $referenceId,
            'transaction_id' => $transaction->id ?? null,
            'current_status' => $transaction->status->value ?? null,
            'state' => $request->state,
        ]);

        $data = array_merge($transaction->trx_data ?? [], [
            'easylink_topup' => $requestData ?? [],
        ]);

        $transaction->update(['trx_data' => $data]);
        Log::debug('EasylinkPaymentGateway - handleTopup - Transaction data updated', [
            'reference_id' => $referenceId,
        ]);

        $state = (int) $request->state;
        Log::info('EasylinkPaymentGateway - handleTopup - Processing state', [
            'reference_id' => $referenceId,
            'state' => $state,
            'state_name' => TransferState::tryFrom($state)?->name ?? 'UNKNOWN',
        ]);

        switch ($state) {
            case TransferState::COMPLETE->value:
                $remarks = 'Easylink Topup Completed';
                Log::info('EasylinkPaymentGateway - handleTopup - Topup completed', [
                    'reference_id' => $referenceId,
                ]);
                app(WebhookService::class)->sendWithdrawalWebhook($transaction, $remarks);
                if ($transaction->status !== TrxStatus::COMPLETED) {
                    return app(TransactionService::class)->completeTransaction(
                        trxId: $request->reference_id,
                        referenceNumber: $request->disbursement_id,
                        remarks: $remarks,
                    );
                }

                return true;

            case TransferState::FAILED->value:
                $remarks = 'Easylink Topup Failed';
                $description = 'Easylink Topup Failed';
                Log::error('EasylinkPaymentGateway - handleTopup - Topup failed', [
                    'reference_id' => $referenceId,
                ]);
                app(WebhookService::class)->sendWithdrawalWebhook($transaction, $remarks);

                return app(TransactionService::class)->failTransaction(
                    trxId: $request->reference_id,
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::REFUND_SUCCESS->value:
                $remarks = 'Easylink Topup Refunded';
                $description = 'Easylink Topup Refunded';
                Log::info('EasylinkPaymentGateway - handleTopup - Topup refunded', [
                    'reference_id' => $referenceId,
                ]);

                return app(TransactionService::class)->refundTransaction(
                    trxId: $request->reference_id,
                    referenceNumber: $request->disbursement_id,
                    remarks: $remarks,
                    description: $description
                );

            default:
                Log::warning('EasylinkPaymentGateway - handleTopup - Unknown state', [
                    'reference_id' => $referenceId,
                    'state' => $state,
                ]);
                return false;
        }
    }
}
