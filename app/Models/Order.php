<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_code',
        'total_price',
        'status',
        'payment_method',
        'proof_of_payment',
        'notes',
        'ordered_at',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'ordered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'proof_of_payment_url',
    ];

    /**
     * Get the user who placed the order
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the full URL for the proof of payment image
     */
    public function getProofOfPaymentUrlAttribute()
    {
        if (!$this->proof_of_payment) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->proof_of_payment, FILTER_VALIDATE_URL)) {
            return $this->proof_of_payment;
        }

        // Otherwise, construct the URL
        return url('storage/' . $this->proof_of_payment);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get total items count in the order
     */
    public function getTotalItemsAttribute()
    {
        return $this->orderItems->sum('quantity');
    }
}