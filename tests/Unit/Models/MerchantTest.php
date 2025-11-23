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

class MerchantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use migrations to ensure proper schema (includes extended users columns)
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

    public function test_merchant_can_be_created_with_required_attributes()
    {
        $user = User::factory()->create();

        $merchantData = [
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Test Business',
            'site_url' => 'https://testbusiness.com',
            'currency_id' => 1,
            'ma_fee' => 2.5,
            'trx_fee' => 1.5,
            'agent_fee' => 0.5,
            'status' => MerchantStatus::PENDING,
        ];

        $merchant = Merchant::create($merchantData);

        $this->assertInstanceOf(Merchant::class, $merchant);
        $this->assertEquals($user->id, $merchant->user_id);
        $this->assertEquals('Test Business', $merchant->business_name);
        $this->assertEquals('https://testbusiness.com', $merchant->site_url);
        $this->assertEquals(1, $merchant->currency_id);
        $this->assertEquals(2.5, $merchant->ma_fee);
        $this->assertEquals(1.5, $merchant->trx_fee);
        $this->assertEquals(0.5, $merchant->agent_fee);
        $this->assertEquals(MerchantStatus::PENDING, $merchant->status);
    }

    public function test_merchant_fillable_attributes()
    {
        $merchant = new Merchant;
        $expectedFillable = [
            'user_id',
            'merchant_key',
            'business_name',
            'site_url',
            'currency_id',
            'business_logo',
            'business_description',
            'business_email',
            'business_whatsapp_group_id',
            'business_telegram_group_id',
            'business_email_group',
            'ma_fee',
            'trx_fee',
            'agent_fee',
            'api_key',
            'api_secret',
            'status',
        ];

        $this->assertEquals($expectedFillable, $merchant->getFillable());
    }

    public function test_merchant_casts()
    {
        $merchant = new Merchant;
        $expectedCasts = [
            'id' => 'int',
            'ma_fee' => 'float',
            'trx_fee' => 'double',
            'agent_fee' => 'double',
            'status' => MerchantStatus::class,
        ];

        $this->assertEquals($expectedCasts, $merchant->getCasts());
    }

    public function test_merchant_belongs_to_user()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);

        $this->assertInstanceOf(User::class, $merchant->user);
        $this->assertEquals($user->id, $merchant->user->id);
    }

    public function test_merchant_belongs_to_agent()
    {
        $owner = User::factory()->create();
        $agent = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $owner->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        $merchant->agent_id = $agent->id;
        $merchant->save();

        $this->assertInstanceOf(User::class, $merchant->agent);
        $this->assertEquals($agent->id, $merchant->agent->id);
    }

    public function test_merchant_has_many_features()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        $feature1 = MerchantFeature::create(['merchant_id' => $merchant->id, 'feature' => 'f1']);
        $feature2 = MerchantFeature::create(['merchant_id' => $merchant->id, 'feature' => 'f2']);

        $this->assertCount(2, $merchant->features);
        $this->assertTrue($merchant->features->contains($feature1));
        $this->assertTrue($merchant->features->contains($feature2));
    }

    public function test_merchant_can_get_specific_feature()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        $feature = MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
        ]);

        $retrievedFeature = $merchant->getFeature('webhooks_enabled');

        $this->assertInstanceOf(MerchantFeature::class, $retrievedFeature);
        $this->assertEquals($feature->id, $retrievedFeature->id);
        $this->assertEquals('webhooks_enabled', $retrievedFeature->feature);
    }

    public function test_merchant_get_feature_returns_null_when_not_found()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);

        $feature = $merchant->getFeature('non_existent_feature');

        $this->assertNull($feature);
    }

    public function test_merchant_has_feature_returns_default_when_feature_not_found()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);

        $result = $merchant->hasFeature('non_existent_feature', false);

        $this->assertFalse($result);
    }

    public function test_merchant_has_feature_returns_effective_value_when_feature_exists()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        MerchantFeature::create([
            'merchant_id' => $merchant->id,
            'feature' => 'webhooks_enabled',
            'status' => true,
        ]);

        $result = $merchant->hasFeature('webhooks_enabled');

        $this->assertTrue($result);
    }

    public function test_merchant_can_be_created_with_optional_attributes()
    {
        $user = User::factory()->create();

        $merchantData = [
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Complete Business',
            'site_url' => 'https://completebusiness.com',
            'currency_id' => 1,
            'business_logo' => 'logo.png',
            'business_description' => 'A complete business description',
            'business_email' => 'business@example.com',
            'business_whatsapp_group_id' => 'whatsapp123',
            'business_telegram_group_id' => 'telegram123',
            'business_email_group' => 'group@example.com',
            'ma_fee' => 3.0,
            'trx_fee' => 2.0,
            'agent_fee' => 1.0,
            'api_key' => 'api_key_123',
            'api_secret' => 'api_secret_456',
            'status' => MerchantStatus::APPROVED,
        ];

        $merchant = Merchant::create($merchantData);

        $this->assertEquals('logo.png', $merchant->business_logo);
        $this->assertEquals('A complete business description', $merchant->business_description);
        $this->assertEquals('business@example.com', $merchant->business_email);
        $this->assertEquals('whatsapp123', $merchant->business_whatsapp_group_id);
        $this->assertEquals('telegram123', $merchant->business_telegram_group_id);
        $this->assertEquals('group@example.com', $merchant->business_email_group);
        $this->assertEquals('api_key_123', $merchant->api_key);
        $this->assertEquals('api_secret_456', $merchant->api_secret);
        $this->assertEquals(MerchantStatus::APPROVED, $merchant->status);
    }

    public function test_merchant_status_enum_casting()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
            'status' => MerchantStatus::PENDING,
        ]);

        $this->assertInstanceOf(MerchantStatus::class, $merchant->status);
        $this->assertEquals(MerchantStatus::PENDING, $merchant->status);
    }

    public function test_merchant_fee_casting()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
            'ma_fee' => '2.5',
            'trx_fee' => '1.5',
            'agent_fee' => '0.5',
        ]);

        $this->assertIsFloat($merchant->ma_fee);
        $this->assertIsFloat($merchant->trx_fee);
        $this->assertIsFloat($merchant->agent_fee);
        $this->assertEquals(2.5, $merchant->ma_fee);
        $this->assertEquals(1.5, $merchant->trx_fee);
        $this->assertEquals(0.5, $merchant->agent_fee);
    }

    public function test_merchant_can_be_updated()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Old Name',
            'currency_id' => 1,
        ]);

        $merchant->update(['business_name' => 'New Name']);

        $this->assertEquals('New Name', $merchant->business_name);
    }

    public function test_merchant_timestamps_are_set()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);

        $this->assertNotNull($merchant->created_at);
        $this->assertNotNull($merchant->updated_at);
    }

    public function test_merchant_can_be_deleted()
    {
        $user = User::factory()->create();
        $merchant = Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Biz',
            'currency_id' => 1,
        ]);
        $merchantId = $merchant->id;

        $merchant->delete();

        $this->assertNull(Merchant::find($merchantId));
    }

    public function test_merchant_user_relationship_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Merchant::create([
            'merchant_key' => $this->generateMerchantKey(),
            'business_name' => 'Test Business',
            'site_url' => 'https://testbusiness.com',
            'currency_id' => 1,
        ]);
    }

    public function test_merchant_business_name_is_required()
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Merchant::create([
            'user_id' => $user->id,
            'merchant_key' => $this->generateMerchantKey(),
            'site_url' => 'https://testbusiness.com',
            'currency_id' => 1,
        ]);
    }

    protected function generateMerchantKey(): string
    {
        return Str::uuid()->toString();
    }
}
