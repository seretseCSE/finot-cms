<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Product extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image',
        'stock_quantity',
        'status',
        'category',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return asset('storage/'.$this->image);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2).' ETB';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public static function getResourceName(): string
    {
        return 'products';
    }

    public static function getNavigationLabel(): string
    {
        return 'Shop Products';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }
}
