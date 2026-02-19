<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegitimacyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'alias',
        'chapter',
        'position',
        'fraternity_number',
        'status',
        'admin_note',
        'certificate_date',
        'certification_details',  // NEW
        'school_name',            // NEW
        'address',                // NEW
        'logo_url1',               // NEW
        'logo_url2',               // NEW
        'approved_at',
    ];

    // Optional: cast approved_at to datetime
    protected $casts = [
        'approved_at' => 'datetime',
    ];

    protected $dates = [
        'certificate_date',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    // Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signatories()
    {
        return $this->hasMany(Signatory::class);
    }
}