
## Webhook Intake Flow

- Endpoint: `POST /ipn/{gateway}/{action?}` receives the raw webhook payload and instantly dispatches an `App\Events\WebhookReceived` event.
- Listener: `App\Listeners\StoreWebhookCallListener` persists the call to the `webhook_calls` table and enqueues `App\Jobs\ProcessWebhookCallJob` for asynchronous processing.
- Processing: Queue workers should include the `webhooks` queue, for example `php artisan queue:work --queue=webhooks,default`.
- Storage: Each call is stored with headers, payload, raw body, HTTP verb, and optional action metadata for replay or debugging purposes.

### Setup Steps

1. Install dependencies: `composer update spatie/laravel-webhook-client`.
2. Run database migrations: `php artisan migrate` (ensure the connection used by `App\Models\WebhookCall` is configured).
3. Start the queue worker: `php artisan queue:work --queue=webhooks,default`.
4. Configure gateway secrets (URLs, tokens, signing keys) so incoming requests pass validation and can be replayed if needed.
