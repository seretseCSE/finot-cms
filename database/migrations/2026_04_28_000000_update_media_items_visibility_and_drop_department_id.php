<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Migrate existing non-Public visibility values to Hidden
        DB::table('media_items')
            ->whereIn('visibility', ['Members Only', 'Department Only'])
            ->update(['visibility' => 'Hidden']);

        // Alter the visibility enum to only allow Public/Hidden
        DB::statement("ALTER TABLE media_items MODIFY visibility ENUM('Public', 'Hidden') NOT NULL DEFAULT 'Public'");

        // Drop the department_id foreign key and column (no longer needed)
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }

    public function down(): void
    {
        // Restore department_id column
        Schema::table('media_items', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
        });

        // Restore the old enum values
        DB::statement("ALTER TABLE media_items MODIFY visibility ENUM('Public', 'Members Only', 'Department Only') NOT NULL DEFAULT 'Public'");
    }
};
