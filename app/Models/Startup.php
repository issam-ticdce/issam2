<?php

namespace App\Models;

use App\Casts\Json;
use App\Enums\Stage;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Moderated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Startup extends Model
{
    use HasTranslations, Moderated;

    protected $guarded = ['id', 'views'];

    protected function casts(): array
    {
        return [
            'tagline' => Json::class,
            'description' => Json::class,
            'needs_details' => Json::class,
            'team' => Json::class,
            'needs' => Json::class,
            'draft' => Json::class,
            'stage' => Stage::class,
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public static function moderatedFields(): array
    {
        return [
            'name', 'logo', 'cover', 'tagline', 'description', 'sector_id', 'stage',
            'founded_year', 'city', 'website', 'email', 'phone', 'facebook', 'linkedin',
            'instagram', 'video_url', 'team', 'needs', 'needs_details',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Startup $startup) {
            $startup->slug ??= static::uniqueSlug($startup->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'startup';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? Storage::disk('public')->url($this->cover) : null;
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->name, 0, 2));
    }
}
