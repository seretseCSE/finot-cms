<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Ethiopian-month escrow cycle of an engagement (the money spine).
 * Prepay: gross = planned_hours × rate, minus credit carried from the
 * previous cycle = amount_due, paid through a gateway (this row is the
 * GatewayPayable). Release: net = confirmed value − commission to the
 * tutor's wallet; unfulfilled value becomes credit_carried for the next
 * cycle. (ec_year, ec_month) is the idempotency anchor, exactly like
 * recurring fee billing.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutoring_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engagement_id')->constrained('tutoring_engagements')->cascadeOnDelete();
            $table->smallInteger('ec_year');
            $table->smallInteger('ec_month');
            $table->string('label', 40); // "Meskerem 2019"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('planned_hours', 6, 2);
            $table->decimal('hourly_rate', 8, 2); // engagement snapshot at billing
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('credit_applied', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2);
            $table->decimal('commission_percent', 5, 2);
            $table->string('status', 20)->default('awaiting_payment'); // CycleStatus
            $table->timestamp('funded_at')->nullable();
            // Release outcome (single writer: CycleReleaser).
            $table->decimal('confirmed_hours', 6, 2)->nullable();
            $table->decimal('confirmed_value', 12, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->decimal('released_amount', 12, 2)->nullable();
            $table->decimal('credit_carried', 12, 2)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refund_note')->nullable();
            $table->timestamps();

            $table->index(['engagement_id', 'status']);
            $table->index(['status', 'ends_on']);
        });

        DB::statement('CREATE UNIQUE INDEX tutoring_cycles_period_unique ON tutoring_cycles (engagement_id, ec_year, ec_month)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_cycles');
    }
};
