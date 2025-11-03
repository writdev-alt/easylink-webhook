<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_with_fillable_attributes()
    {
        $customerData = [
            'name' => 'John Doe',
            'whatsapp' => '+1234567890',
            'whatsapp_verified' => true,
            'email' => 'john@example.com',
            'email_verified' => false,
        ];

        $customer = Customer::create($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('+1234567890', $customer->whatsapp);
        $this->assertTrue($customer->whatsapp_verified);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertFalse($customer->email_verified);
    }

    public function test_customer_fillable_attributes()
    {
        $customer = new Customer;
        $expectedFillable = [
            'name',
            'whatsapp',
            'whatsapp_verified',
            'email',
            'email_verified',
        ];

        $this->assertEquals($expectedFillable, $customer->getFillable());
    }

    public function test_customer_has_transactions_relationship()
    {
        $customer = Customer::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $customer->transactions()
        );
    }

    public function test_customer_name_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Customer::create([
            'whatsapp' => '+1234567890',
            'email' => 'test@example.com',
        ]);
    }

    public function test_customer_whatsapp_verified_defaults_to_false()
    {
        $customer = Customer::create([
            'name' => 'Test User',
        ]);

        $this->assertFalse($customer->whatsapp_verified);
    }

    public function test_customer_email_verified_defaults_to_false()
    {
        $customer = Customer::create([
            'name' => 'Test User',
        ]);

        $this->assertFalse($customer->email_verified);
    }

    public function test_customer_can_have_nullable_whatsapp()
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'whatsapp' => null,
        ]);

        $this->assertNull($customer->whatsapp);
    }

    public function test_customer_can_have_nullable_email()
    {
        $customer = Customer::create([
            'name' => 'Test User',
            'email' => null,
        ]);

        $this->assertNull($customer->email);
    }

    public function test_customer_timestamps_are_set()
    {
        $customer = Customer::create([
            'name' => 'Test User',
        ]);

        $this->assertNotNull($customer->created_at);
        $this->assertNotNull($customer->updated_at);
    }

    public function test_customer_can_be_updated()
    {
        $customer = Customer::create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $customer->update([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'email_verified' => true,
        ]);

        $this->assertEquals('Updated Name', $customer->name);
        $this->assertEquals('updated@example.com', $customer->email);
        $this->assertTrue($customer->email_verified);
    }

    public function test_customer_can_be_deleted()
    {
        $customer = Customer::create([
            'name' => 'Test User',
        ]);

        $customerId = $customer->id;
        $customer->delete();

        $this->assertNull(Customer::find($customerId));
    }
}
