<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_tours')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->dropColumn('total_tours');
        });
    }
};
