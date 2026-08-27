<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade levels are nationally fixed (KG-1 .. Grade 12) and seeded once at the
 * platform level. They are NEVER school- or branch-scoped. `code` is the stable
 * machine key. `has_national_exam` flags Grade 6/8/12 (Grade 10 has none).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('cycle');
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('has_national_exam')->default(false);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
