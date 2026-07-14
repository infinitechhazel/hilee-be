<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartnershipInquiry extends Model
{
    use HasFactory;

    protected $table = 'partnership_inquiries';

    protected $fillable = [
        'name',
        'email',
        'company',
        'type',
        'message',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'retail'      => 'Retail Partnership',
            'distributor' => 'Distributor Partnership',
            'reseller'    => 'Reseller / Online',
            default       => 'Other',
        };
    }
}