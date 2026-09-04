<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A standing discount/scholarship POLICY for a person — the layer above
 * per-invoice discounts. The subject is a student OR a guardian (guardian-level
 * covers every linked child). Scope: specific fee types (text[] — null = all
 * fees), one academic year / term, or lifetime (both null). Concessions are
 * resolved at INVOICE GENERATION time and stamped onto the invoice
 * (best single concession wins, no stacking) — the invoice stays the frozen
 * source of truth, revoking a concession never rewrites billed history.
 *
 * Auto-suggested concessions (sibling / staff-child school policy) are born
 * `pending` and only take effect once finance approves them.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fee_concessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            // Null = every branch of the school (guardian-level / lifetime grants).
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();

            // Exactly one subject: the student, or a guardian (all their children).
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('parents')->restrictOnDelete();

            // Why the concession exists — drives reporting and the review queue.
            $table->string('category', 30); // sibling|staff_child|merit|hardship|scholarship|other
            $table->string('discount_type', 20); // percentage|fixed|full_scholarship
            $table->decimal('discount_value', 12, 2)->default(0);

            // Which fees it touches: null = all fee types.
            $table->jsonb('fee_types')->nullable();

            // Validity window: year (and optionally term) — both null = lifetime.
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('status', 20)->default('pending'); // pending|active|rejected|revoked
            $table->string('source', 20)->default('manual'); // manual|auto_sibling|auto_staff
            $table->string('reason')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
            $table->index('student_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_concessions');
    }
};
