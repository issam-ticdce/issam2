<?php

namespace App\Models;

use App\Casts\Json;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasTranslations;

    protected $fillable = ['slug', 'name', 'sort'];

    protected function casts(): array
    {
        return ['name' => Json::class];
    }

    public function startups(): HasMany
    {
        return $this->hasMany(Startup::class);
    }
}
