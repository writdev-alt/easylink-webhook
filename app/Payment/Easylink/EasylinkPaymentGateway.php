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
        $this->credentials = $customCredentials ?? config('payment_gateways.easylink.credentials');
        $this->baseUrl = $this->getBaseUrl();
        $this->getAccessToken();
    }

    /**
     * Get the appropriate API endpoint based on the application environment.
     */
    protected function getBaseUrl(): string
    {
        $sandboxEndpoint = 'http://sandbox.easylink.id:9080';
        $productionEndpoint = 'https://openapi.easylink.id';

        if (config('app.mode') === 'production') {
            return $productionEndpoint;
        }

        return $sandboxEndpoint;
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
        try {
            $response = Http::post($this->baseUrl.'/get-access-token', [
                'app_id' => $this->credentials['app_id'],
                'app_secret' => $this->credentials['app_secret'],
            ]);
            $payload = $response->json();
            $token = $payload['data'] ?? null;

            if (is_array($token)) {
                $token = $token['access_token'] ?? $token['token'] ?? reset($token);
            }

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Invalid access token response from Easylink API.');
            }

            $this->accessToken = $token;
            Log::info('Easylink Access Token Response', ['response' => $payload]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to connect to Easylink API.');
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
        $common = [
            'X-EasyLink-AppKey' => $this->credentials['app_key'],
            'X-EasyLink-Nonce' => (string) Str::uuid()->toString(),
            'X-EasyLink-Timestamp' => (string) Carbon::now()->getPreciseTimestamp(3),
        ];

        $signature = $this->signature($common, $body);

        return [
            'Content-type' => 'application/json',
            'Authorization' => 'Bearer '.$this->accessToken,
            'X-EasyLink-AppKey' => $common['X-EasyLink-AppKey'],
            'X-EasyLink-Nonce' => $common['X-EasyLink-Nonce'],
            'X-EasyLink-Timestamp' => $common['X-EasyLink-Timestamp'],
            'X-EasyLink-Sign' => $signature,
        ];
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
        $params = array_merge($body, $common);
        ksort($params);

        $originStr = collect($params)
            ->map(fn ($value, $key) => $key.'='.urlencode((string) $value))
            ->implode('&');

        $signData = $this->credentials['app_key'].$originStr.$this->credentials['app_key'];

        $attemptedPath = 'inline';
        $credentialKey = $this->credentials['private_key'] ?? null;

        if (! empty($credentialKey)) {
            // If credentials provide inline PEM content
            if (str_starts_with($credentialKey, '-----BEGIN')) {
                $privateKeyContent = $credentialKey;
            } else {
                // Treat credential value as a path (support file:// prefix)
                $path = $credentialKey;
                if (str_starts_with($path, 'file://')) {
                    $path = substr($path, 7);
                }
                $attemptedPath = $path;

                if (file_exists($path)) {
                    $privateKeyContent = file_get_contents($path);
                } else {
                    // Fallback: use the credential string as content
                    $privateKeyContent = $credentialKey;
                }
            }
        } else {
            // Resolve key path by app mode with fallback
            if (config('app.mode') === 'sandbox') {
                $privateKeyPath = storage_path('app/keys/sandbox/private_key.pem');
            } else {
                $privateKeyPath = storage_path('app/keys/production/private_key.pem');
            }
            $attemptedPath = $privateKeyPath;

            if (! file_exists($privateKeyPath)) {
                // Fallback to default keys path
                $fallbackPath = storage_path('app/keys/private_key.pem');
                $attemptedPath = $fallbackPath;
                if (! file_exists($fallbackPath)) {
                    throw new RuntimeException('Private key file not found at: '.$privateKeyPath.' and fallback: '.$fallbackPath);
                }
                $privateKeyPath = $fallbackPath;
            }

            $privateKeyContent = file_get_contents($privateKeyPath);
        }

        $passphrase = $this->credentials['private_key_passphrase'] ?? null;
        $privateKey = $passphrase !== null
            ? openssl_pkey_get_private($privateKeyContent, $passphrase)
            : openssl_pkey_get_private($privateKeyContent);

        if (! $privateKey) {
            throw new RuntimeException('Invalid private key. Attempted: '.$attemptedPath);
        }

        if (! openssl_sign($signData, $signatureBytes, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to generate signature.');
        }

        return base64_encode($signatureBytes);
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
        $cacheFilePath = storage_path('app/'.$cacheFileName);

        // Check if cache file exists
        if (file_exists($cacheFilePath)) {
            $cachedData = json_decode(file_get_contents($cacheFilePath));

            return (object) $cachedData->data;
        }

        // File doesn't exist, make API request
        $this->getAccessToken();

        $response = Http::timeout(60)->withHeaders($this->headers($body))
            ->post($this->baseUrl.$endpoint, $body);

        $responseData = $response->object();
        Log::info(json_encode($responseData));

        if (isset($responseData->err_code)) {
            $errorMessage = $this->handleInternalErrorCode($responseData->err_code ?? 1);
            Log::error($errorMessage);
            throw new Exception($errorMessage);
        } elseif (($responseData->code ?? 1) !== 0) {
            $errorMessage = $this->handleInternalErrorCode($responseData->code ?? 1);
            Log::error($errorMessage);
            throw new Exception($errorMessage);
        }

        // Cache response data to JSON file
        $directory = dirname($cacheFilePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($cacheFilePath, json_encode($responseData, JSON_PRETTY_PRINT));

        return (object) $responseData->data;
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
        return $this->makeEasylinkRequest(
            '/v2/data/supported-bank-code',
            ['country' => $country],
            "easylink/bank_list_{$country}.json"
        );
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
        return $this->makeEasylinkRequest(
            '/data/branch-list',
            ['country' => $country, 'bank_code' => $bankCode],
            "easylink/branch_list_{$country}_{$bankCode}.json"
        );
    }

    /**
     * Lists all supported wallets from the Bank Partner API.
     *
     * @throws Exception
     */
    public function getWalletList(): object
    {
        return $this->makeEasylinkRequest(
            '/v2/data/supported-inst-code',
            [],
            'easylink/wallet_list.json'
        );
    }

    /**
     * Lists all supported Virtual Accounts from the Bank Partner API.
     *
     * @throws Exception
     */
    public function getVaAccountList(): object
    {
        return $this->makeEasylinkRequest(
            '/virtual-account/get-available-virtual-account-banks',
            [],
            'easylink/va_account_list.json'
        );
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
        $body = [
            'account_number' => $accountNumber,
            'bank_id' => $bankId,
            'payment_method' => $paymentMethod,
        ];

        $this->getAccessToken();

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($this->baseUrl.'/v2/transfer/verify-bank-account', $body);

            $responseData = $response->object();
            Log::info('Verify Bank Account Response Data', ['responseData' => $responseData]);

            if ($responseData->code === 0) {
                return true;
            } else {
                $errorMessage = $this->handleInternalErrorCode($responseData->code);
                Log::error($errorMessage);

                return false;
            }
        } catch (Exception $e) {
            throw new Exception($this->handleApiResponseError($e));
        }
    }

    public function getRemittanceList(string $startDate, string $endDate, ?string $pageSize = null, ?string $pageNumber = null): object|bool
    {
        $body = [
            'start_datetime' => $startDate,
            'end_datetime' => $endDate,
            'page_size' => $pageSize ?? 10,
            'page_number' => $pageNumber ?? 1,
        ];

        $this->getAccessToken();

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($this->baseUrl.'/transfer/get-remittance-list', $body);

            $response->throw();

            $responseData = $response->object();
            Log::info('Easylink Response Data', ['responseData' => $responseData]);

            if (isset($responseData->err_code)) {
                throw new Exception($responseData->message);
            }

            if (($responseData->code ?? 1) === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;

                return $responseData->data;
            } elseif ($errorMessage = $this->handleInternalErrorCode($responseData->code)) {
                Log::error($errorMessage);

                if ($errorMessage === 'Duplicate reference.') {

                    if (isset($transaction->trx_data['easylink_disbursement'])) {
                        return (object) $transaction->trx_data['easylink_disbursement'];
                    }

                    return (object) [
                        'state' => TransferState::CREATE, // Default state for create
                        'message' => 'Duplicate Reference',
                    ];
                }

                return (object) [
                    'state' => TransferState::FAILED, // Default state for failure
                    'message' => $errorMessage,
                ];
            } else {
                $errorMessage = 'Unknown internal error: '.$responseData->code.' with response message: '.$responseData->message;
                Log::error($errorMessage);

                return (object) [
                    'state' => TransferState::FAILED, // Default state for failure
                    'message' => $errorMessage,
                ];
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'Duplicate reference.') {
                Log::error($e->getMessage());

                if (isset($transaction->trx_data['easylink_disbursement'])) {
                    return (object) $transaction->trx_data['easylink_disbursement'];
                }

                return (object) [
                    'state' => TransferState::CREATE, // Default state for create
                    'message' => 'Duplicate Reference',
                ];
            }

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
        $withdrawalAccount = $transaction->trx_data['withdrawal_account'];

        $bankId = $withdrawalAccount['account_bank_code'] ?? null;
        $accNo = $withdrawalAccount['account_number'] ?? null;
        $accName = $withdrawalAccount['account_holder_name'] ?? null;

        if ($this->isSandbox()) {
            // In sandbox mode, override the account details with test values
            $bankId = '2'; // Example bank code for MANDIRI
            $accNo = '8730700000000001'; // Example account number
            $accName = 'Andohar Erwin Juniarta'; // Example account holder name
        }

        if (! $bankId || ! $accNo || ! $accName) {
            throw new InvalidArgumentException('Missing required transaction data for Bank Partner transfer.');
        }

        $payoutMethodCode = PayoutMethod::fromProviderName($transaction->provider);
        if ($payoutMethodCode === 0) {
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

        $this->getAccessToken();
        Log::info('Easylink Body', $body);
        Log::info('Easylink Headers', $this->headers($body));
        Log::info('Easylink BaseUrl', ['base_url' => $this->baseUrl]);
        Log::info('Easylink Access Token', ['token' => $this->accessToken]);

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($this->baseUrl.'/v2/transfer/create-domestic-transfer', $body);

            $response->throw();

            $responseData = $response->object();

            if (isset($responseData->err_code)) {
                throw new Exception($responseData->message);
            }

            if (($responseData->code ?? 1) === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;

                return $responseData->data;
            } elseif ($errorMessage = $this->handleInternalErrorCode($responseData->code)) {
                Log::error($errorMessage);

                if ($errorMessage === 'Duplicate reference.') {

                    if (isset($transaction->trx_data['easylink_disbursement'])) {
                        return (object) $transaction->trx_data['easylink_disbursement'];
                    }

                    return (object) [
                        'state' => TransferState::CREATE, // Default state for create
                        'message' => 'Duplicate Reference',
                    ];
                }

                return (object) [
                    'state' => TransferState::FAILED, // Default state for failure
                    'message' => $errorMessage,
                ];
            } else {
                $errorMessage = 'Unknown internal error: '.$responseData->code.' with response message: '.$responseData->message;
                Log::error($errorMessage);

                return (object) [
                    'state' => TransferState::FAILED, // Default state for failure
                    'message' => $errorMessage,
                ];
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'Duplicate reference.') {
                Log::error($e->getMessage());

                if (isset($transaction->trx_data['easylink_disbursement'])) {
                    return (object) $transaction->trx_data['easylink_disbursement'];
                }

                return (object) [
                    'state' => TransferState::CREATE, // Default state for create
                    'message' => 'Duplicate Reference',
                ];
            }

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
        $body = [
            'reference' => $referenceId,
        ];
        $this->getAccessToken();

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($body))
                ->post($this->baseUrl.'/transfer/get-domestic-transfer', $body);

            $response->throw();

            $responseData = $response->object();
            Log::info('Easylink Get Domestic Transfer Update Response Data', ['responseData' => $responseData]);

            if ($responseData->code === 0) {
                $stateLabel = $this->getStateLabel($responseData->data->state ?? 0);
                $responseData->data->state_label = $stateLabel;
            } else {
                $errorMessage = $this->handleInternalErrorCode($responseData->code);

                Log::error($errorMessage);

                return (object) [
                    'state' => TransferState::FAILED, // Default state for failure
                    'message' => $errorMessage,
                ];
            }

            return $responseData;
        } catch (Exception $e) {
            // throw new Exception($this->handleApiResponseError($e));
            Log::error($e->getMessage());

            return (object) [
                'state' => TransferState::FAILED, // Default state for failure
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
        $settlementData = $this->getDomesticTransferUpdate($transaction->trx_id);

        $payload = $settlementData->data ?? null;

        if (empty((array) $payload)) {
            Log::error('Easylink Domestic Transfer Update payload empty.', [
                'settlementData' => $settlementData,
                'transactionId' => $transaction->trx_id,
            ]);

            $remarks = 'Withdrawal request is failed on Financial Institution: '.($settlementData->data->message ?? '');
            $description = 'Withdrawal request is failed on Bank Partner: '.($settlementData->data->message ?? '');

            app(TransactionService::class)->refundTransaction($transaction->trx_id, $remarks, $description);

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
        $this->getAccessToken();
        $body = [];

        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers($body))
                ->post($this->baseUrl.'/finance-account/balances', $body);

            Log::info('Easylink Account Balances Response', ['response' => $response]);

            $response->throw();

            Log::info('Easylink Account Balances Response', ['response' => $response->body()]);

            $responseData = $response->object();

            if (($responseData->code ?? 1) !== 0) {
                $errorMessage = $this->handleInternalErrorCode($responseData->code ?? 1);
                throw new Exception($errorMessage);
            } elseif (isset($responseData->data)) {
                return (object) $responseData->data;
            }

            return (object) $responseData->data;
        } catch (Exception $e) {
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
        $this->getAccessToken();
        $data = [
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

        try {
            $response = Http::timeout(60)->withHeaders($this->headers($data))
                ->post($this->baseUrl, $data);

            Log::info('Easylink Webhook Validation Response', ['response' => $response->object()]);

            if (isset($response->status) && $response->status === 'success') {
                return true;
            }

            return false;
        } catch (Exception $e) {
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
        $data = array_merge($transaction->trx_data ?? [], [
            'easylink_settlement' => $settlementData ?? [],
        ]);
        $transaction->update([
            'trx_data' => $data,
        ]);

        $rawState = $settlementData['state'] ?? null;

        try {
            $stateEnum = match (true) {
                $rawState instanceof TransferState => $rawState,
                is_int($rawState) => TransferState::fromStatusCode($rawState),
                is_string($rawState) && is_numeric($rawState) => TransferState::fromStatusCode((int) $rawState),
                default => null,
            };
        } catch (ValueError $exception) {
            $stateEnum = null;
        }

        if (! $stateEnum instanceof TransferState) {
            throw new \Exception(
                'Unknown Transfer State: '.var_export($settlementData['state'], true).
                ' for Transaction ID: '.$settlementData['reference_id']
            );
        }

        /**
         * Map the internal TransferState (Easylink) to the external TrxStatus and remarks.
         * Each handled state returns a boolean indicating whether the notification
         * was processed successfully.
         */
        switch ($stateEnum) {
            case TransferState::CREATE: // 1
                $remarks = 'Withdrawal request is created by Financial Institution';

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

                return app(TransactionService::class)->failTransaction(
                    trxId: $settlementData['reference_id'],
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::FAILED: // 9
                $remarks = 'Withdrawal request is failed on Financial Institution: '.($settlementData['message'] ?? '');
                $description = 'Withdrawal request is failed on Bank Partner: '.($settlementData['message'] ?? '');

                return app(TransactionService::class)->failTransaction(
                    trxId: $settlementData['reference_id'],
                    remarks: $remarks,
                    description: $description
                );

            case TransferState::REFUND_SUCCESS: // 10
                $remarks = 'Withdrawal request has been cancelled and refunded by Financial Institution';
                $description = 'Withdrawal request has been cancelled and refunded by Bank Partner';

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

                return app(TransactionService::class)->completeTransaction(
                    trxId: $settlementData['reference_id'],
                    referenceNumber: $referenceNumber,
                    remarks: $remarks,
                    description: $description
                );

            default:
                $remarks = 'Withdrawal request is in '.$stateEnum->label().' state by Financial Institution';
                $description = 'Withdrawal request is in '.$stateEnum->label().' state by Bank Partner';
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
        Log::info('Easylink Response', $settlementData);
        // if (app()->inProduction()) {
        //     if (! $this->validateWebHook($settlementData)) {
        //         Log::error('Easylink Webhook validation failed', $settlementData);
        //         throw new NotifyErrorException('Easylink Webhook validation failed');
        //     }
        // }
        if ($transaction = Transaction::where('trx_id', $request->reference_id)->first()) {
            return $this->updateSettlementTransactionData($transaction, $settlementData);
        }

        return false;
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
        Log::info('Easylink Topup Response', $request->toArray());

        if ($transaction = Transaction::where('trx_id', $request->reference_id)->first()) {
            $data = array_merge($transaction->trx_data ?? [], [
                'easylink_topup' => $request->toArray() ?? [],
            ]);

            $transaction->update(['trx_data' => $data]);

            switch ((int) $request->state) {
                case TransferState::COMPLETE->value:
                    $remarks = 'Easylink Topup Completed';
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
                    app(WebhookService::class)->sendWithdrawalWebhook($transaction, $remarks);

                    return app(TransactionService::class)->failTransaction(
                        trxId: $request->reference_id,
                        remarks: $remarks,
                        description: $description
                    );

                case TransferState::REFUND_SUCCESS->value:
                    $remarks = 'Easylink Topup Refunded';
                    $description = 'Easylink Topup Refunded';

                    return app(TransactionService::class)->refundTransaction(
                        trxId: $request->reference_id,
                        referenceNumber: $request->disbursement_id,
                        remarks: $remarks,
                        description: $description
                    );

            }
        }

        return false;
    }
}
