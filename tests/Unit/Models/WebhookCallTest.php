<?php

namespace Tests\Unit\Models;

use App\Models\WebhookCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_call_can_be_created_with_required_attributes()
    {
        $webhookData = [
            'uuid' => 'webhook-uuid-123',
            'name' => 'easylink.callback',
            'url' => 'https://api.example.com/webhook/easylink/callback',
            'http_verb' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Signature' => 'signature123',
            ],
            'payload' => [
                'transaction_id' => 'TXN123456',
                'status' => 'success',
                'amount' => 1000.00,
            ],
            'raw_body' => '{"transaction_id":"TXN123456","status":"success","amount":1000.00}',
            'meta' => [
                'gateway' => 'easylink',
                'processed_at' => '2024-01-01 12:00:00',
            ],
        ];

        $webhookCall = WebhookCall::create($webhookData);

        $this->assertInstanceOf(WebhookCall::class, $webhookCall);
        $this->assertEquals('webhook-uuid-123', $webhookCall->uuid);
        $this->assertEquals('easylink.callback', $webhookCall->name);
        $this->assertEquals('https://api.example.com/webhook/easylink/callback', $webhookCall->url);
        $this->assertEquals('POST', $webhookCall->http_verb);
        $this->assertEquals(['Content-Type' => 'application/json', 'X-Signature' => 'signature123'], $webhookCall->headers);
        $this->assertEquals(['transaction_id' => 'TXN123456', 'status' => 'success', 'amount' => 1000.00], $webhookCall->payload);
        $this->assertEquals('{"transaction_id":"TXN123456","status":"success","amount":1000.00}', $webhookCall->raw_body);
        $this->assertEquals(['gateway' => 'easylink', 'processed_at' => '2024-01-01 12:00:00'], $webhookCall->meta);
    }

    public function test_webhook_call_fillable_attributes()
    {
        $webhookCall = new WebhookCall;
        $expectedFillable = [
            'uuid',
            'name',
            'url',
            'http_verb',
            'headers',
            'payload',
            'raw_body',
            'meta',
            'exception',
        ];

        $this->assertEquals($expectedFillable, $webhookCall->getFillable());
    }

    public function test_webhook_call_casts()
    {
        $webhookCall = new WebhookCall;
        $expectedCasts = [
            'id' => 'int',
            'headers' => 'array',
            'payload' => 'array',
            'meta' => 'array',
        ];

        $this->assertEquals($expectedCasts, $webhookCall->getCasts());
    }

    public function test_webhook_call_uses_mysql_site_connection()
    {
        $webhookCall = new WebhookCall;

        $this->assertEquals('mysql_site', $webhookCall->getConnectionName());
    }

    public function test_webhook_call_can_store_exception_data()
    {
        $webhookData = [
            'name' => 'failed.webhook',
            'url' => 'https://api.example.com/webhook/failed',
            'http_verb' => 'POST',
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
            'http_verb' => 'POST',
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
            'http_verb' => 'POST',
            'payload' => $complexPayload,
        ]);

        $this->assertEquals($complexPayload, $webhookCall->payload);
        $this->assertEquals('TXN123456', $webhookCall->payload['transaction']['id']);
        $this->assertEquals('John Doe', $webhookCall->payload['customer']['name']);
        $this->assertEquals(5.00, $webhookCall->payload['transaction']['fees']['admin_fee']);
    }

    public function test_webhook_call_can_store_meta_information()
    {
        $metaData = [
            'gateway' => 'netzme',
            'retry_count' => 3,
            'last_retry_at' => '2024-01-01 12:30:00',
            'processing_time_ms' => 250,
            'response_code' => 200,
            'response_body' => '{"status":"success"}',
        ];

        $webhookCall = WebhookCall::create([
            'name' => 'meta.test',
            'url' => 'https://api.example.com/webhook',
            'http_verb' => 'POST',
            'meta' => $metaData,
        ]);

        $this->assertEquals($metaData, $webhookCall->meta);
        $this->assertEquals('netzme', $webhookCall->meta['gateway']);
        $this->assertEquals(3, $webhookCall->meta['retry_count']);
        $this->assertEquals(250, $webhookCall->meta['processing_time_ms']);
    }

    public function test_webhook_call_can_handle_different_http_verbs()
    {
        $httpVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($httpVerbs as $verb) {
            $webhookCall = WebhookCall::create([
                'name' => "test.{$verb}",
                'url' => 'https://api.example.com/webhook',
                'http_verb' => $verb,
            ]);

            $this->assertEquals($verb, $webhookCall->http_verb);
        }
    }

    public function test_webhook_call_can_be_updated()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'update.test',
            'url' => 'https://api.example.com/webhook',
            'http_verb' => 'POST',
        ]);

        $webhookCall->update([
            'exception' => 'Processing failed',
            'meta' => ['retry_count' => 1],
        ]);

        $this->assertEquals('Processing failed', $webhookCall->exception);
        $this->assertEquals(['retry_count' => 1], $webhookCall->meta);
    }

    public function test_webhook_call_timestamps_are_set()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'timestamp.test',
            'url' => 'https://api.example.com/webhook',
            'http_verb' => 'POST',
        ]);

        $this->assertNotNull($webhookCall->created_at);
        $this->assertNotNull($webhookCall->updated_at);
    }

    public function test_webhook_call_can_handle_null_values()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'null.test',
            'url' => 'https://api.example.com/webhook',
            'http_verb' => 'POST',
            'uuid' => null,
            'headers' => null,
            'payload' => null,
            'raw_body' => null,
            'meta' => null,
            'exception' => null,
        ]);

        $this->assertNull($webhookCall->uuid);
        $this->assertNull($webhookCall->headers);
        $this->assertNull($webhookCall->payload);
        $this->assertNull($webhookCall->raw_body);
        $this->assertNull($webhookCall->meta);
        $this->assertNull($webhookCall->exception);
    }

    public function test_webhook_call_can_be_deleted()
    {
        $webhookCall = WebhookCall::create([
            'name' => 'delete.test',
            'url' => 'https://api.example.com/webhook',
            'http_verb' => 'POST',
        ]);

        $webhookCallId = $webhookCall->id;
        $webhookCall->delete();

        $this->assertNull(WebhookCall::find($webhookCallId));
    }
}
