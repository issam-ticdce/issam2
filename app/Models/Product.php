<?php

namespace App\Models;

use App\Casts\Json;
use App\Enums\PriceType;
use App\Enums\ProductType;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Moderated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasTranslations, Moderated;

    protected $guarded = ['id', 'views'];

    protected function casts(): array
    {
        return [
            'name' => Json::class,
            'summary' => Json::class,
            'description' => Json::class,
            'images' => Json::class,
            'draft' => Json::class,
            'type' => ProductType::class,
            'price_type' => PriceType::class,
            'price' => 'decimal:3',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public static function moderatedFields(): array
    {
        return [
            'type', 'name', 'summary', 'description', 'images', 'price_type', 'price',
            'link_url', 'video_url', 'sort',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= Str::slug($product->tr('name', 'fr') ?? '') ?: 'produit';
            $product->slug .= '-'.Str::lower(Str::random(5));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }

    /** Visible publiquement : le produit ET sa startup sont publiés. */
    public function scopeVisible($query)
    {
        return $query->published()->whereHas('startup', fn ($q) => $q->published());
    }

    public function imageUrls(): array
    {
        return collect($this->images ?? [])->map(fn ($path) => Storage::disk('public')->url($path))->all();
    }

    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    public function priceLabel(): string
    {
        $amount = $this->price !== null
            ? Number::format((float) $this->price, maxPrecision: 3, locale: app()->getLocale() === 'ar' ? 'fr' : app()->getLocale()) // chiffres latins, comme en Tunisie.' '.__('site.currency')
            : null;

        return match ($this->price_type) {
            PriceType::Fixed => $amount ?? __('site.price_types.quote'),
            PriceType::From => $amount ? __('site.price_from', ['price' => $amount]) : __('site.price_types.quote'),
            PriceType::Free => __('site.price_types.free'),
            default => __('site.price_types.quote'),
        };
    }
}
