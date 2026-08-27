<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory categories follow the subjects pattern: platform seed rows
 * (school_id NULL — stationery, furniture, lab equipment…) that every school
 * shares, plus school-owned custom rows. Deactivate, never delete, once used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('icon', 40)->nullable(); // Lucide icon slug for the picker
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('school_id');
        });

        // Partial uniques: platform names unique among platform rows, school
        // names unique per school — trashed rows never block recreation.
        DB::statement('CREATE UNIQUE INDEX inventory_categories_platform_name_unique ON inventory_categories (name) WHERE school_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_categories_school_name_unique ON inventory_categories (school_id, name) WHERE school_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
