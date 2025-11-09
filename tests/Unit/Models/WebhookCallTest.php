<?php

namespace Tests\Unit\Models;

use App\Models\WebhookCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.webhook_calls_connection' => config('database.default')]);
    }

    public function test_webhook_call_can_be_created_with_required_attributes()
    {
        $webhookData = [
            'name' => 'easylink.callback',
            'url' => 'https://api.example.com/webhook/easylink/callback',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Signature' => 'signature123',
            ],
            'payload' => [
                'transaction_id' => 'TXN123456',
                'status' => 'success',
                'amount' => 1000.00,
            ],
            'exception' => null,
            'trx_id' => 'TXN123456',
        ];

        $webhookCall = WebhookCall::create($webhookData);

        $this->assertInstanceOf(WebhookCall::class, $webhookCall);
        $this->assertEquals('easylink.callback', $webhookCall->name);
        $this->assertEquals('https://api.example.com/webhook/easylink/callback', $webhookCall->url);
        $this->assertEquals(['Content-Type' => 'application/json', 'X-Signature' => 'signature123'], $webhookCall->headers);
        $this->assertEquals(['transaction_id' => 'TXN123456', 'status' => 'success', 'amount' => 1000.00], $webhookCall->payload);
        $this->assertNull($webhookCall->exception);
        $this->assertEquals('TXN123456', $webhookCall->trx_id);
    }

    public function test_webhook_call_fillable_attributes()
    {
        $webhookCall = new WebhookCall;
        $this->assertEqualsCanonicalizing([
            'name',
            'url',
            'http_verb',
            'headers',
            'payload',
            'exception',
            'trx_id',
        ], $webhookCall->getFillable());
    }

    public function test_webhook_call_casts()
    {
        $webhookCall = new WebhookCall;
        $this->assertArrayHasKey('headers', $webhookCall->getCasts());
        $this->assertArrayHasKey('payload', $webhookCall->getCasts());
        $this->assertSame('array', $webhookCall->getCasts()['headers']);
        $this->assertSame('array', $webhookCall->getCasts()['payload']);
    }

    public function test_webhook_call_uses_configured_connection()
    {
        $webhookCall = new WebhookCall;

        $this->assertEquals(config('database.default'), $webhookCall->getConnectionName());
    }

    public function test_webhook_call_can_store_exception_data()
    {
        $webhookData = [
            'name' => 'failed.webhook',
            'url' => 'https://api.example.com/webhook/failed',
            'exception' => 'Connection timeout after 30 seconds',
        ];

        $webhookCall = WebhookCall::create($webhookData);

        $this->assertEquals('Connection timeout after 30 seconds', $webhookCall->exception);
    }

    public function test_webhook_call_can_store_complex_headers()
    {
        $complexHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123',
            'X-Custom-Header' => 'custom-value',
            'X-Timestamp' => '1640995200',
            'User-Agent' => 'WebhookClient/1.0',
        ];

        $webhookCall = WebhookCall::create([
            'name' => 'complex.headers',
            'url' => 'https://api.example.com/webhook',
            'headers' => $complexHeaders,
        ]);

        $this->assertEquals($complexHeaders, $webhookCall->headers);
        $this->assertEquals('Bearer token123', $webhookCall->headers['Authorization']);
        $this->assertEquals('custom-value', $webhookCall->headers['X-Custom-Header']);
    }

    public function test_webhook_call_can_store_complex_payload()
    {
        $complexPayload = [
            'transaction' => [
                'id' => 'TXN123456',
                'type' => 'deposit',
                'amount' => 1000.00,
                'currency' => 'IDR',
                'fees' => [
                    'admin_fee' => 5.00,
                    'processing_fee' => 10.00,
                ],
            ],
            'customer' => [
                'id' => 'CUST789',
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'metadata' => [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
                'timestamp' => '2024-01-01T12:00:00Z',
            ],
        ];

        $webhookCall = WebhookCall::create([
            'name' => 'complex.payload',
            'url' => 'https://api.example.com/webhook',
            'payload' => $complexPayload,
        ]);

        $this->assertEquals($complexPayload, $webhookCall->payload);
        $this->assertEquals('TXN123456', $webhookCall->payload['transaction']['id']);
        $this->assertEquals('John Doe', $webhookCall->payload['customer']['name']);
        $this->assertEquals(5.00, $webhookCall->payload['transaction']['fees']['admin_fee']);
    }

    public function test_webhook_call_can_store_netzme_success_payload()
    {
        $payloadPath = base_path('tests/mockups/netzme/success.json');
        $payload = json_decode((string) file_get_contents($payloadPath), true, 512, JSON_THROW_ON_ERROR);

        $webhookCall = WebhookCall::create([
            'name' => 'netzme',
            'url' => 'https://api.example.com/webhook/netzme',
            'payload' => $payload,
            'trx_id' => $payload['originalPartnerReferenceNo'] ?? null,
        ]);

        $this->assertEquals($payload, $webhookCall->payload);
        $this->assertEquals('netzme', $webhookCall->name);
        $this->assertEquals('https://api.example.com/webhook/netzme', $webhookCall->url);
        $this->assertEquals(
            $payload['originalPartnerReferenceNo'] ?? null,
            $webhookCall->trx_id
        );
    }

    public function test_webhook_call_can_store_trx_id()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'trx.test',
            'url' => 'https://api.example.com/webhook',
            'trx_id' => 'TRX-123',
        ]);

        $this->assertEquals('TRX-123', $webhookCall->trx_id);
    }

    public function test_webhook_call_can_be_updated()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'update.test',
            'url' => 'https://api.example.com/webhook',
        ]);

        $webhookCall->update([
            'exception' => 'Processing failed',
            'payload' => ['retry_count' => 1],
        ]);

        $this->assertEquals('Processing failed', $webhookCall->exception);
        $this->assertEquals(['retry_count' => 1], $webhookCall->payload);
    }

    public function test_webhook_call_timestamps_are_set()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'timestamp.test',
            'url' => 'https://api.example.com/webhook',
        ]);

        $this->assertNotNull($webhookCall->created_at);
        $this->assertNotNull($webhookCall->updated_at);
    }

    public function test_webhook_call_can_handle_null_values()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'null.test',
            'url' => 'https://api.example.com/webhook',
            'headers' => null,
            'payload' => null,
            'exception' => null,
            'trx_id' => null,
        ]);

        $this->assertNull($webhookCall->headers);
        $this->assertNull($webhookCall->payload);
        $this->assertNull($webhookCall->exception);
        $this->assertNull($webhookCall->trx_id);
    }

    public function test_webhook_call_can_be_deleted()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'delete.test',
            'url' => 'https://api.example.com/webhook',
        ]);

        $webhookCallId = $webhookCall->id;
        $webhookCall->delete();

        $this->assertNull(WebhookCall::find($webhookCallId));
    }
}
