<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property int|null $rank_id References the ID in the ranks table
 * @property string|null $old_ranks
 * @property string|null $referral_code
 * @property string|null $avatar
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $username
 * @property string|null $gender
 * @property string|null $birthday
 * @property string|null $phone
 * @property string|null $whatsapp WhatsApp number for notifications
 * @property string|null $telegram Telegram username for notifications
 * @property string|null $whatsapp_verified_at WhatsApp verification timestamp
 * @property string|null $telegram_verified_at Telegram verification timestamp
 * @property string|null $country
 * @property string|null $address
 * @property string $email
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $phone_verified_at
 * @property string|null $google2fa_secret
 * @property int $two_factor_enabled
 * @property int $status
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogle2faSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOldRanks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereReferralCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelegram($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTelegramVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWhatsapp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWhatsappVerifiedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'int',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /**
     * Default attribute values to ensure app-level defaults without DB roundtrip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'two_factor_enabled' => false,
        'status' => true,
    ];

    /**
     * Allow assignment of google2fa_secret via mass update without listing in fillable.
     */
    public function isFillable($key)
    {
        if ($key === 'google2fa_secret') {
            return true;
        }

        return parent::isFillable($key);
    }

    /**
     * Override update to permit assigning google2fa_secret via mass update
     * while keeping the expected fillable list intact.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if (array_key_exists('google2fa_secret', $attributes)) {
            $secret = $attributes['google2fa_secret'];
            unset($attributes['google2fa_secret']);
            $this->forceFill(['google2fa_secret' => $secret]);
        }

        $this->fill($attributes);
        return $this->save($options);
    }
}
