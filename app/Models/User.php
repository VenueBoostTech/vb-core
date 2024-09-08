<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'country_code',
        'name',
        'email',
        'email_verified_at',
        'password',
        'enduser',
        'role_id',
        'old_platform_registration_type',
        'gender',
        'profile_photo_path',
        'username',
        'company_name',
        'company_vat',
        'status',
        'old_platform_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function quizResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserResponse::class);
    }

    public function firebaseTokens()
    {
        return $this->hasMany(FirebaseUserToken::class);
    }

    public function role()
    {
        return $this->belongsTo(HighLevelRole::class, 'role_id');
    }

    public function notificationSchedules()
    {
        return $this->hasMany(NotificationSchedule::class);
    }

    public function quizUserSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserSession::class);
    }

    public function guest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Guest::class);
    }

    // Other model properties and methods

    public function endUserChats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'end_user_id');
    }

    public function venueUserChats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'venue_user_id');
    }

    public function sentMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function member(): \Illuminate\Database\Eloquent\Relations\HasOne {
        return $this->hasOne(Member::class);
    }
}
