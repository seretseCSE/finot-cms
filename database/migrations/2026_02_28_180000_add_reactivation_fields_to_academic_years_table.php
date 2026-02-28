<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->timestamp('reactivated_at')->nullable()->after('deactivated_at');
            $table->foreignId('reactivated_by')->nullable()->after('reactivated_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropForeign(['reactivated_by']);
            $table->dropColumn(['reactivated_at', 'reactivated_by']);
        });
    }
};
