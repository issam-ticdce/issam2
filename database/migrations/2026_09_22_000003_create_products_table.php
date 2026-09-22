<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('startup_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('type'); // service | application | solution | cultural | project | other
            $table->text('name');         // traduit
            $table->text('summary')->nullable();     // traduit
            $table->text('description')->nullable(); // traduit
            $table->text('images')->nullable();      // chemins des images
            $table->string('price_type')->default('quote'); // quote | fixed | from | free
            $table->decimal('price', 12, 3)->nullable();     // en dinars (TND)
            $table->string('link_url')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('sort')->default(0);

            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->text('draft')->nullable();
            $table->string('review_status')->default('none');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
