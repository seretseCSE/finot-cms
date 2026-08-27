<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MoE textbook lending: one row per student × book × academic year. Issued
 * in bulk to a section (ONE aggregate ledger movement carries the quantity),
 * returned at year end, or marked lost (a ledger write-off). section_id is
 * a SNAPSHOT of where the student sat at issue time — the loan follows the
 * student, not the section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textbook_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 12)->default('out'); // out|returned|lost
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'academic_year_id', 'inventory_item_id']);
            $table->index(['section_id', 'status']);
            $table->index('student_id');
        });

        // A student holds at most one OPEN loan of a given book per year —
        // returning (or losing) it frees the slot for a re-issue.
        DB::statement("CREATE UNIQUE INDEX textbook_loans_open_unique ON textbook_loans (academic_year_id, inventory_item_id, student_id) WHERE status = 'out' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('textbook_loans');
    }
};
