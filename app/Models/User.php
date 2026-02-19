<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'zip_code',
        'role',
        'email_verified',
        'verification_token',
        'verification_token_expiry',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token', // never expose token in responses
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_token_expiry' => 'datetime',
        'email_verified' => 'boolean',
        'password' => 'hashed',
    ];

    protected $dates = ['deleted_at'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified === true;
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeUsers($query)
    {
        return $query->where('role', 'user');
    }
}
