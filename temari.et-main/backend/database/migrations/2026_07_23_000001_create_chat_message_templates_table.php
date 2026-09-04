<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * School-owned preset messages for staff chat (branch rows override nothing —
 * they simply ADD branch-specific presets next to the school-wide set).
 * `body` holds one text per platform language {en, am, om} with placeholder
 * tokens ({student_name}, {teacher_name}, {school_name}, {date}) resolved per
 * conversation at pick time.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 32)->default('general');
            $table->jsonb('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id', 'is_active']);
        });

        // Partial unique (soft-delete convention): a trashed template never
        // blocks re-creating the same name; NULL branch collapses to 0 so
        // school-wide names are unique too.
        DB::statement(
            'create unique index chat_message_templates_scope_name_unique'
            .' on chat_message_templates (school_id, coalesce(branch_id, 0), name)'
            .' where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_templates');
    }
};
