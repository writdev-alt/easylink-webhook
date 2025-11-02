<?php

namespace App\Http\Controllers;

use App\Events\WebhookReceived;
use App\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IPNController
{
    public function __construct(private readonly PaymentGatewayFactory $paymentFactory) {}

    /**
     * Handle IPN based on the specific gateway.
     *
     * This method handles IPN based on the specific gateway.
     *
     * @param  Request  $request  The request containing the IPN data
     * @param  string  $gateway  The gateway to handle the IPN
     * @param  null  $action  The action to handle the IPN
     * @return JsonResponse|RedirectResponse
     *
     * @throws \Throwable
     */
    public function handleIPN(Request $request, string $gateway, $action = null)
    {
        try {
            // Ensure we only acknowledge supported gateways
            try {
               $paymentGateway = $this->paymentFactory->getGateway($gateway);
            } catch (\Throwable $unsupported) {
                Log::warning('IPN received for unsupported gateway', [
                    'gateway' => $gateway,
                    'action' => $action,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unsupported payment gateway',
                ], 404);
            }

            Log::info('IPN received', [
                'gateway' => $gateway,
                'action' => $action,
                'http_verb' => $request->method(),
                'query' => $request->query->all(),
            ]);

            $payload = array_merge($request->all(), [
                '_action' => $action,
            ]);

            $rawBody = $request->getContent();

            event(new WebhookReceived(
                gateway: $gateway,
                action: is_string($action) ? trim($action) : null,
                url: $request->fullUrl(),
                headers: $request->headers->all(),
                payload: $payload,
                httpVerb: $request->method(),
                query: $request->query->all(),
                rawBody: $rawBody !== '' ? $rawBody : null,
            ));

            // Handle IPN based on the specific gateway
            if (empty($action)) {
                $result = $paymentGateway->handleIPN($request);
            } else {
                $actionMethod = 'handle'.ucfirst(trim($action));
                $result = call_user_func_array([$paymentGateway, $actionMethod], [$request]);
            }

            Log::info('Webhook queued for processing', [
                'gateway' => $gateway,
                'action' => $action,
            ]);


            return response()->json([
                'status' => $result,
                'message' => 'Webhook received',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('IPN handling failed', [
                'gateway' => $gateway,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'IPN processing failed',
            ], 500);
        }
    }
}
