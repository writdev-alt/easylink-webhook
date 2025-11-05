<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_required_attributes()
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ];

        $user = User::create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_fillable_attributes()
    {
        $user = new User;
        $expectedFillable = [
            'first_name',
            'last_name',
            'username',
            'email',
            'password',
            'phone',
            'whatsapp',
            'telegram',
            'gender',
            'birthday',
            'country',
            'address',
            'role',
            'status',
            'two_factor_enabled',
            'referral_code',
            'avatar',
        ];

        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    public function test_user_hidden_attributes()
    {
        $user = new User;
        $expectedHidden = [
            'password',
            'remember_token',
            'google2fa_secret',
        ];

        $this->assertEquals($expectedHidden, $user->getHidden());
    }

    public function test_user_casts()
    {
        $user = new User;
        $expectedCasts = [
            'id' => 'int',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'status' => 'boolean',
        ];

        $this->assertEquals($expectedCasts, $user->getCasts());
    }

    public function test_user_password_is_hashed()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'plainpassword',
        ]);

        $this->assertNotEquals('plainpassword', $user->password);
        $this->assertTrue(Hash::check('plainpassword', $user->password));
    }

    public function test_user_two_factor_enabled_defaults_to_false()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertFalse($user->two_factor_enabled);
    }

    public function test_user_status_defaults_to_true()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue($user->status);
    }

    public function test_user_can_have_optional_attributes()
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'whatsapp' => '+1234567890',
            'telegram' => '@johndoe',
            'gender' => 'male',
            'birthday' => '1990-01-01',
            'country' => 'US',
            'address' => '123 Main St',
            'role' => 'user',
            'referral_code' => 'REF123',
        ];

        $user = User::create($userData);

        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertEquals('+1234567890', $user->phone);
        $this->assertEquals('+1234567890', $user->whatsapp);
        $this->assertEquals('@johndoe', $user->telegram);
        $this->assertEquals('male', $user->gender);
        $this->assertEquals('1990-01-01', $user->birthday);
        $this->assertEquals('US', $user->country);
        $this->assertEquals('123 Main St', $user->address);
        $this->assertEquals('user', $user->role);
        $this->assertEquals('REF123', $user->referral_code);
    }

    public function test_user_username_must_be_unique()
    {
        User::create([
            'username' => 'uniqueuser',
            'email' => 'user1@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'username' => 'uniqueuser',
            'email' => 'user2@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_user_email_must_be_unique()
    {
        User::create([
            'username' => 'user1',
            'email' => 'unique@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'username' => 'user2',
            'email' => 'unique@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_user_can_be_updated()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $user->update([
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'role' => 'admin',
        ]);

        $this->assertEquals('Updated', $user->first_name);
        $this->assertEquals('Name', $user->last_name);
        $this->assertEquals('admin', $user->role);
    }

    public function test_user_timestamps_are_set()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_user_can_enable_two_factor_authentication()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $user->update([
            'two_factor_enabled' => true,
            'google2fa_secret' => 'secret123',
        ]);

        $this->assertTrue($user->two_factor_enabled);
        $this->assertEquals('secret123', $user->google2fa_secret);
    }

    public function test_user_can_be_deactivated()
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $user->update(['status' => false]);

        $this->assertFalse($user->status);
    }
}