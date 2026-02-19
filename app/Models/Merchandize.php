<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchandize extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'category',
        'price',
        'stock',
        'image_url',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Scope a query to only include active merchandise.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include merchandise in stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope a query to filter by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to search by name or description.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Get the formatted price attribute.
     *
     * @return string
     */
    public function getFormattedPriceAttribute()
    {
        return '₱' . number_format($this->price, 2);
    }

    /**
     * Check if the merchandise is in stock.
     *
     * @return bool
     */
    public function isInStock()
    {
        return $this->stock > 0;
    }

    /**
     * Check if the merchandise is low stock.
     *
     * @param  int  $threshold
     * @return bool
     */
    public function isLowStock($threshold = 10)
    {
        return $this->stock > 0 && $this->stock < $threshold;
    }

    /**
     * Decrease stock by a given quantity.
     *
     * @param  int  $quantity
     * @return bool
     */
    public function decreaseStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->stock -= $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Increase stock by a given quantity.
     *
     * @param  int  $quantity
     * @return bool
     */
    public function increaseStock($quantity)
    {
        $this->stock += $quantity;
        return $this->save();
    }
}