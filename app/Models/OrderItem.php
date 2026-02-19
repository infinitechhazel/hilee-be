<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Get the order that owns the order item
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product associated with the order item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Boot method to handle events
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate subtotal before saving
        static::saving(function ($orderItem) {
            $orderItem->subtotal = $orderItem->quantity * $orderItem->price;
        });
    }
}