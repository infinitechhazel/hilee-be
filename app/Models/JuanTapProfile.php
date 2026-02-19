<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JuanTapProfile extends Model
{
    use HasFactory;

    // Specify the correct table name
    protected $table = 'juantap_profiles';

    protected $fillable = [
        'user_id',
        'profile_url',
        'qr_code',
        'status',
        'subscription',
    ];

    protected $casts = [
        'status' => 'string',
        'subscription' => 'string',
    ];

    /**
     * Get the user that owns the profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}