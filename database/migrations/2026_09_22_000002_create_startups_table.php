<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('cover')->nullable();
            $table->text('tagline')->nullable();      // traduit
            $table->text('description')->nullable();  // traduit
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('instagram')->nullable();
            $table->string('video_url')->nullable();
            $table->text('team')->nullable();         // [{name, role, photo}]
            $table->text('needs')->nullable();        // ["funding", "partners", ...]
            $table->text('needs_details')->nullable(); // traduit

            // Publication et modération
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->text('draft')->nullable();        // modifications en attente de validation
            $table->string('review_status')->default('none'); // none | pending | rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('startup_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('startup_id');
        });
        Schema::dropIfExists('startups');
    }
};
