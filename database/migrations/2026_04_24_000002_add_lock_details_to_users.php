<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'lock_reason')) {
                $table->string('lock_reason')->nullable()->after('locked_until');
            }
            if (! Schema::hasColumn('users', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->after('lock_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('locked_at');
            $table->dropConstrainedForeignIdIfExists('locked_by');
            $table->dropColumnIfExists('lock_reason');
        });
    }
};
