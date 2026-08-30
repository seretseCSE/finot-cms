<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('predefined_reports');
        Schema::dropIfExists('temporary_filters');
    }

    public function down(): void
    {
        // Intentionally empty: unused scaffolding is not restored.
    }
};
