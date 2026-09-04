<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform seed catalog of known K-12 health conditions (asthma, epilepsy,
 * food allergies…) grouped by category. Schools never own rows here; a
 * student's actual conditions live on the student_health_conditions pivot.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('health_conditions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_conditions');
    }
};
