<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;

class Admin extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, CanResetPassword;

   protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'otp',
        'otp_expires_at',
        'email_verification_hash',
        'role',
        'user_id',
        'profile_picture',
        'phone_number',
        'status',
        'date_of_birth',
        'gender',
        'driving_license',
        'work_place',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'otp',
        'otp_expires_at',
        'email_verification_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'last_login_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role ?? 'admin',
            'email_verified' => !is_null($this->email_verified_at),
            "guard" => "admin",
            "model" => Admin::class
        ];
    }
}
