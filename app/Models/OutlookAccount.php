<?php
// app/Models/OutlookAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OutlookAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Check if token is expired or about to expire (within 5 minutes)
    public function isTokenExpired()
    {
        return now()->addMinutes(5)->greaterThan($this->token_expires_at);
    }

    // Scope to get only active accounts
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
