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
        Schema::table('departments', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('departments', 'icon')) {
                $table->string('icon')->nullable()->after('description');
            }
            if (!Schema::hasColumn('departments', 'head_user_id')) {
                $table->foreignId('head_user_id')->nullable()->after('icon')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('departments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        // Update existing departments with default codes if they're null
        if (Schema::hasColumn('departments', 'code')) {
            \DB::table('departments')->whereNull('code')->update([
                'code' => \DB::raw('CONCAT("dept-", id)')
            ]);
            
            // Add unique constraint if it doesn't exist
            try {
                Schema::table('departments', function (Blueprint $table) {
                    $table->unique('code');
                });
            } catch (\Exception $e) {
                // Unique constraint already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'head_user_id')) {
                $table->dropForeign(['head_user_id']);
                $table->dropColumn(['head_user_id']);
            }
            if (Schema::hasColumn('departments', 'icon')) {
                $table->dropColumn('icon');
            }
            if (Schema::hasColumn('departments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
