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
        Schema::table('member_parent_guardians', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('member_id')->constrained('parents')->onDelete('set null');
            $table->boolean('is_external')->default(true)->after('phone')->comment('True if parent not in parents table');
            $table->softDeletes();
            
            $table->index(['parent_id']);
            $table->index(['is_external']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_parent_guardians', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'is_external']);
            $table->dropSoftDeletes();
        });
    }
};
