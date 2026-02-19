<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'quantity'   => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Accessors
    public function getFormattedPriceAttribute(): string
    {
        return '₱' . number_format($this->price, 2);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity == 0) return 'out_of_stock';
        if ($this->quantity < 10) return 'low_stock';
        return 'in_stock';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->where('quantity', '>', 0)->where('quantity', '<', 10);
    }

    // Helpers
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    public function isLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity < 10;
    }
}