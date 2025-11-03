<?php

namespace Tests\Unit\Models;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\MerchantFeature;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MerchantFeatureTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Use migrations for accurate schema (users has two_factor_enabled, etc.)
        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_merchant_feature_can_be_created_with_required_attributes()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        
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
        $feature = new MerchantFeature();
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
        $feature = new MerchantFeature();
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
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        $feature = MerchantFeature::create(['merchant_id' => $merchant->id, 'feature' => 'x']);

        $this->assertInstanceOf(Merchant::class, $feature->merchant);
        $this->assertEquals($merchant->id, $feature->merchant->id);
    }

    public function test_dynamic_status_returns_false_for_webhooks_when_merchant_not_approved()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ]);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_true_for_webhooks_when_merchant_approved()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::APPROVED,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ]);

        $this->assertTrue($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_false_for_api_ip_whitelist_when_merchant_not_approved()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'api_ip_whitelist_enabled',
            'status' => true,
        ]);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_false_for_multi_currency_when_merchant_not_approved()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'multi_currency_enabled',
            'status' => true,
        ]);

        $this->assertFalse($feature->dynamic_status);
    }

    public function test_dynamic_status_returns_status_for_other_features()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'custom_feature',
            'status' => true,
        ]);

        $this->assertTrue($feature->dynamic_status);
    }

    public function test_effective_value_returns_status_for_boolean_type()
    {
        $feature = MerchantFeature::create([
            'type' => 'boolean',
            'status' => true,
            'value' => ['some' => 'data'],
        ]);

        $this->assertTrue($feature->effective_value);
    }

    public function test_effective_value_returns_value_for_non_boolean_type()
    {
        $customValue = ['api_key' => 'test123', 'secret' => 'secret456'];
        $feature = MerchantFeature::create([
            'type' => 'array',
            'status' => false,
            'value' => $customValue,
        ]);

        $this->assertEquals($customValue, $feature->effective_value);
    }

    public function test_effective_value_returns_status_when_value_is_null()
    {
        $feature = MerchantFeature::create([
            'type' => 'string',
            'status' => true,
            'value' => null,
        ]);

        $this->assertTrue($feature->effective_value);
    }

    public function test_scope_for_merchant_filters_by_merchant_id()
    {
        $user = User::factory()->create();
        $merchant1 = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz1',
            'currency_id' => 1,
        ]);
        $merchant2 = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz2',
            'currency_id' => 1,
        ]);
        
        $feature1 = MerchantFeature::create(['merchant_id' => $merchant1->id, 'sort_order' => 2, 'feature' => 'a']);
        $feature2 = MerchantFeature::create(['merchant_id' => $merchant1->id, 'sort_order' => 1, 'feature' => 'b']);
        $feature3 = MerchantFeature::create(['merchant_id' => $merchant2->id, 'feature' => 'c']);

        $features = MerchantFeature::forMerchant($merchant1->id)->get();

        $this->assertCount(2, $features);
        $this->assertEquals($feature2->id, $features->first()->id); // Ordered by sort_order
        $this->assertEquals($feature1->id, $features->last()->id);
    }

    public function test_scope_by_category_filters_by_category()
    {
        MerchantFeature::create(['category' => 'notifications', 'feature' => 'n1']);
        MerchantFeature::create(['category' => 'notifications', 'feature' => 'n2']);
        MerchantFeature::create(['category' => 'security', 'feature' => 's1']);

        $notificationFeatures = MerchantFeature::byCategory('notifications')->get();
        $securityFeatures = MerchantFeature::byCategory('security')->get();

        $this->assertCount(2, $notificationFeatures);
        $this->assertCount(1, $securityFeatures);
    }

    public function test_scope_enabled_filters_by_status()
    {
        MerchantFeature::create(['status' => true, 'feature' => 'e1']);
        MerchantFeature::create(['status' => true, 'feature' => 'e2']);
        MerchantFeature::create(['status' => false, 'feature' => 'e3']);

        $enabledFeatures = MerchantFeature::enabled()->get();

        $this->assertCount(2, $enabledFeatures);
        $this->assertTrue($enabledFeatures->every(fn($feature) => $feature->status === true));
    }

    public function test_is_enabled_returns_dynamic_status()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ]);

        $this->assertFalse($feature->isEnabled()); // Should be false due to merchant status
    }

    public function test_get_value_as_type_returns_boolean_for_boolean_type()
    {
        $feature = MerchantFeature::create([
            'type' => 'boolean',
            'status' => true,
        ]);

        $this->assertIsBool($feature->getValueAsType());
        $this->assertTrue($feature->getValueAsType());
    }

    public function test_get_value_as_type_returns_integer_for_integer_type()
    {
        $feature = MerchantFeature::create([
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

        $feature = MerchantFeature::create([
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
        $feature = MerchantFeature::create(['status' => false, 'feature' => 'u1']);

        $feature->update(['status' => true]);

        $this->assertTrue($feature->status);
    }

    public function test_merchant_feature_timestamps_are_set()
    {
        $feature = MerchantFeature::create(['feature' => 't1']);

        $this->assertNotNull($feature->created_at);
        $this->assertNotNull($feature->updated_at);
    }

    public function test_merchant_feature_can_be_deleted()
    {
        $feature = MerchantFeature::create(['feature' => 'd1']);
        $featureId = $feature->id;

        $feature->delete();

        $this->assertNull(MerchantFeature::find($featureId));
    }

    public function test_merchant_feature_sort_order_casting()
    {
        $feature = MerchantFeature::create(['sort_order' => '5', 'feature' => 'so']);

        $this->assertIsInt($feature->sort_order);
        $this->assertEquals(5, $feature->sort_order);
    }

    public function test_merchant_feature_status_casting()
    {
        $feature = MerchantFeature::create(['status' => 1, 'feature' => 'st']);

        $this->assertIsBool($feature->status);
        $this->assertTrue($feature->status);
    }
}