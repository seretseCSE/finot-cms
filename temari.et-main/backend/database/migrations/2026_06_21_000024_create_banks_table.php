<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform catalog of Ethiopian banks + mobile wallets (seeded once, logos
 * under /images/banks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique(); // stable machine key, logo filename
            $table->string('name');
            $table->string('type', 10)->default('bank'); // bank|wallet
            $table->string('logo')->nullable(); // public path, null = initials fallback
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
