<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed                       → secteurs uniquement (production)
     * php artisan db:seed --class=DemoSeeder    → startups et produits fictifs (test)
     */
    public function run(): void
    {
        $this->call(SectorSeeder::class);
    }
}
