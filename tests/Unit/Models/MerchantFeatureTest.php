<?php

namespace Tests\Unit\Models;

use App\Constants\CurrencyType;
use App\Constants\Status;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\MerchantFeature;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use migrations for accurate schema (users has two_factor_enabled, etc.)
        Artisan::call('migrate:fresh');

        DB::table('currencies')->insert([
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'type' => CurrencyType::FIAT,
            'auto_wallet' => 0,
            'exchange_rate' => 1,
            'rate_live' => false,
            'default' => Status::INACTIVE,
            'status' => Status::INACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_merchant_feature_can_be_created_with_required_attributes()
    {
        $user = User::factory()->create();
        $merchant = $this->createMerchant(['business_name' => 'Biz'], $user);

        $featureData = [
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'description' => 'Enable webhook notifications',
            'type' => 'boolean',
            'category' => 'notifications',
            'status' => true,
            'sort_order' => 1,
        ];

        $feature = MerchantFeature::create($featureData);

        $this->assertInstanceOf(MerchantFeature::class, $feature);
        $this->assertEquals($merchant->id, $feature->merchant_id);
        $this->assertEquals('webhooks_enabled', $feature->feature);
        $this->assertEquals('Enable webhook notifications', $feature->description);
        $this->assertEquals('boolean', $feature->type);
        $this->assertEquals('notifications', $feature->category);
        $this->assertTrue($feature->status);
        $this->assertEquals(1, $feature->sort_order);
    }

    public function test_merchant_feature_fillable_attributes()
    {
        $feature = new MerchantFeature;
        $expectedFillable = [
            'merchant_id',
            'feature',
            'description',
            'status',
            'value',
            'sort_order',
            'category',
            'type',
        ];

        $this->assertEquals($expectedFillable, $feature->getFillable());
    }

    public function test_merchant_feature_casts()
    {
        $feature = new MerchantFeature;
        $expectedCasts = [
            'id' => 'int',
            'status' => 'boolean',
            'sort_order' => 'integer',
            'value' => 'array',
        ];

        $this->assertEquals($expectedCasts, $feature->getCasts());
    }

    public function test_merchant_feature_belongs_to_merchant()
    {
        $merchant = $this->createMerchant(['business_name' => 'Biz']);
        $feature = $this->createFeature(['merchant_id' => $merchant->id, 'feature' => 'x'], $merchant);

        $this->assertInstanceOf(Merchant::class, $feature->merchant);
        $this->assertEquals($merchant->id, $feature->merchant->id);
    }

    public function test_dynamic_status_returns_false_for_webhooks_when_merchant_not_approved()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ], $merchant);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_true_for_webhooks_when_merchant_approved()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::APPROVED,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ], $merchant);

        $this->assertTrue($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_false_for_api_ip_whitelist_when_merchant_not_approved()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'api_ip_whitelist_enabled',
            'status' => true,
        ], $merchant);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_false_for_multi_currency_when_merchant_not_approved()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'multi_currency_enabled',
            'status' => true,
        ], $merchant);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_status_for_other_features()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'custom_feature',
            'status' => true,
        ], $merchant);

        $this->assertTrue($feature->dynamic_status);
    }

    public function test_effective_value_returns_status_for_boolean_type()
    {
        $feature = $this->createFeature([
            'type' => 'boolean',
            'status' => true,
            'value' => ['some' => 'data'],
        ]);

        $this->assertTrue($feature->effective_value);
    }

    public function test_effective_value_returns_value_for_non_boolean_type()
    {
        $customValue = ['api_key' => 'test123', 'secret' => 'secret456'];
        $feature = $this->createFeature([
            'type' => 'array',
            'status' => false,
            'value' => $customValue,
        ]);

        $this->assertEquals($customValue, $feature->effective_value);
    }

    public function test_effective_value_returns_status_when_value_is_null()
    {
        $feature = $this->createFeature([
            'type' => 'string',
            'status' => true,
            'value' => null,
        ]);

        $this->assertTrue($feature->effective_value);
    }

    public function test_scope_for_merchant_filters_by_merchant_id()
    {
        $merchant1 = $this->createMerchant(['business_name' => 'Biz1']);
        $merchant2 = $this->createMerchant(['business_name' => 'Biz2']);

        $feature1 = $this->createFeature(['merchant_id' => $merchant1->id, 'sort_order' => 2, 'feature' => 'a'], $merchant1);
        $feature2 = $this->createFeature(['merchant_id' => $merchant1->id, 'sort_order' => 1, 'feature' => 'b'], $merchant1);
        $feature3 = $this->createFeature(['merchant_id' => $merchant2->id, 'feature' => 'c'], $merchant2);

        $features = MerchantFeature::forMerchant($merchant1->id)->get();

        $this->assertCount(2, $features);
        $this->assertEquals($feature2->id, $features->first()->id); // Ordered by sort_order
        $this->assertEquals($feature1->id, $features->last()->id);
    }

    public function test_scope_by_category_filters_by_category()
    {
        $this->createFeature(['category' => 'notifications', 'feature' => 'n1']);
        $this->createFeature(['category' => 'notifications', 'feature' => 'n2']);
        $this->createFeature(['category' => 'security', 'feature' => 's1']);

        $notificationFeatures = MerchantFeature::byCategory('notifications')->get();
        $securityFeatures = MerchantFeature::byCategory('security')->get();

        $this->assertCount(2, $notificationFeatures);
        $this->assertCount(1, $securityFeatures);
    }

    public function test_scope_enabled_filters_by_status()
    {
        $this->createFeature(['status' => true, 'feature' => 'e1']);
        $this->createFeature(['status' => true, 'feature' => 'e2']);
        $this->createFeature(['status' => false, 'feature' => 'e3']);

        $enabledFeatures = MerchantFeature::enabled()->get();

        $this->assertCount(2, $enabledFeatures);
        $this->assertTrue($enabledFeatures->every(fn ($feature) => $feature->status === true));
    }

    public function test_is_enabled_returns_dynamic_status()
    {
        $merchant = $this->createMerchant([
            'business_name' => 'Biz',
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = $this->createFeature([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ], $merchant);

        $this->assertFalse($feature->isEnabled()); // Should be false due to merchant status
    }

    public function test_get_value_as_type_returns_boolean_for_boolean_type()
    {
        $feature = $this->createFeature([
            'type' => 'boolean',
            'status' => true,
        ]);

        $this->assertIsBool($feature->getValueAsType());
        $this->assertTrue($feature->getValueAsType());
    }

    public function test_get_value_as_type_returns_integer_for_integer_type()
    {
        $feature = $this->createFeature([
            'type' => 'integer',
            'status' => true,
            'value' => ['number' => 42],
        ]);

        // Assuming the method returns the effective value cast to int
        $this->assertIsInt($feature->getValueAsType());
    }

    public function test_merchant_feature_can_store_array_values()
    {
        $arrayValue = [
            'webhook_url' => 'https://example.com/webhook',
            'secret_key' => 'secret123',
            'retry_attempts' => 3,
        ];

        $feature = $this->createFeature([
            'type' => 'array',
            'value' => $arrayValue,
            'feature' => 'array_store_test',
        ]);

        $this->assertEquals($arrayValue, $feature->value);
        $this->assertEquals('https://example.com/webhook', $feature->value['webhook_url']);
        $this->assertEquals(3, $feature->value['retry_attempts']);
    }

    public function test_merchant_feature_can_be_updated()
    {
        $feature = $this->createFeature(['status' => false, 'feature' => 'u1']);

        $feature->update(['status' => true]);

        $this->assertTrue($feature->status);
    }

    public function test_merchant_feature_timestamps_are_set()
    {
        $feature = $this->createFeature(['feature' => 't1']);

        $this->assertNotNull($feature->created_at);
        $this->assertNotNull($feature->updated_at);
    }

    public function test_merchant_feature_can_be_deleted()
    {
        $feature = $this->createFeature(['feature' => 'd1']);
        $featureId = $feature->id;

        $feature->delete();

        $this->assertNull(MerchantFeature::find($featureId));
    }

    public function test_merchant_feature_sort_order_casting()
    {
        $feature = $this->createFeature(['sort_order' => '5', 'feature' => 'so']);

        $this->assertIsInt($feature->sort_order);
        $this->assertEquals(5, $feature->sort_order);
    }

    public function test_merchant_feature_status_casting()
    {
        $feature = $this->createFeature(['status' => 1, 'feature' => 'st']);

        $this->assertIsBool($feature->status);
        $this->assertTrue($feature->status);
    }

    protected function createMerchant(array $attributes = [], ?User $user = null): Merchant
    {
        $user ??= User::factory()->create();

        $defaults = [
            'user_id' => $user->id,
            'merchant_key' => (string) Str::uuid(),
            'business_name' => 'Merchant '.Str::random(5),
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ];

        $data = array_merge($defaults, $attributes);

        return Merchant::create($data);
    }

    protected function createFeature(array $attributes = [], ?Merchant $merchant = null): MerchantFeature
    {
        if (! array_key_exists('merchant_id', $attributes)) {
            $merchant ??= $this->createMerchant();
            $attributes['merchant_id'] = $merchant->id;
        }

        if (! array_key_exists('feature', $attributes)) {
            $attributes['feature'] = 'feature_'.Str::uuid();
        }

        return MerchantFeature::create($attributes);
    }
}
