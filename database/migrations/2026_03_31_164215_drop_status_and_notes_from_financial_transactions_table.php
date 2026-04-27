<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Need to handle index dropping differently
            $this->migrateSQLite();
        } else {
            // MySQL/PostgreSQL: Standard approach
            Schema::table('financial_transactions', function (Blueprint $table) {
                // Drop index first
                $table->dropIndex(['status', 'transaction_date']);
                $table->dropColumn(['status', 'notes']);
            });
        }
    }

    /**
     * Handle SQLite migration
     */
    protected function migrateSQLite(): void
    {
        // Disable foreign key checks
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            // Get existing data
            $data = DB::table('financial_transactions')->get();

            // Get column info for remaining columns
            $columns = DB::select("PRAGMA table_info('financial_transactions')");
            $columnNames = array_column($columns, 'name');

            // Drop existing table
            Schema::dropIfExists('financial_transactions');

            // Recreate table without status and notes columns
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['income', 'expense']);
                $table->string('transaction_id', 20)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3)->default('ETB');
                $table->string('category')->nullable();
                $table->string('source')->nullable();
                $table->date('transaction_date');
                $table->string('payment_method')->nullable();
                $table->foreignId('bank_account_id')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_type')->nullable();
                $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['type', 'transaction_date']);
                $table->index('bank_account_id');
            });

            // Restore data (mapping only the columns that still exist)
            foreach ($data as $row) {
                $insertData = [
                    'id' => $row->id,
                    'type' => $row->type,
                    'transaction_id' => $row->transaction_id,
                    'title' => $row->title,
                    'description' => $row->description ?? null,
                    'amount' => $row->amount,
                    'currency' => $row->currency ?? 'ETB',
                    'category' => $row->category ?? null,
                    'source' => $row->source ?? null,
                    'transaction_date' => $row->transaction_date,
                    'payment_method' => $row->payment_method ?? null,
                    'bank_account_id' => $row->bank_account_id ?? null,
                    'attachment_path' => $row->attachment_path ?? null,
                    'attachment_type' => $row->attachment_type ?? null,
                    'recorded_by' => $row->recorded_by,
                    'approved_by' => $row->approved_by ?? null,
                    'approved_at' => $row->approved_at ?? null,
                    'created_at' => $row->created_at ?? null,
                    'updated_at' => $row->updated_at ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                ];

                DB::table('financial_transactions')->insert($insertData);
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->migrateDownSQLite();
        } else {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('attachment_type');
                $table->text('notes')->nullable()->after('status');
                $table->index(['status', 'transaction_date']);
            });
        }
    }

    /**
     * Handle SQLite down migration
     */
    protected function migrateDownSQLite(): void
    {
        // Disable foreign key checks
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            // Get existing data
            $data = DB::table('financial_transactions')->get();

            // Drop existing table
            Schema::dropIfExists('financial_transactions');

            // Recreate table with status and notes columns
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['income', 'expense']);
                $table->string('transaction_id', 20)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3)->default('ETB');
                $table->string('category')->nullable();
                $table->string('source')->nullable();
                $table->date('transaction_date');
                $table->string('payment_method')->nullable();
                $table->foreignId('bank_account_id')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_type')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['type', 'transaction_date']);
                $table->index(['status', 'transaction_date']);
                $table->index('bank_account_id');
            });

            // Restore data
            foreach ($data as $row) {
                DB::table('financial_transactions')->insert([
                    'id' => $row->id,
                    'type' => $row->type,
                    'transaction_id' => $row->transaction_id,
                    'title' => $row->title,
                    'description' => $row->description ?? null,
                    'amount' => $row->amount,
                    'currency' => $row->currency ?? 'ETB',
                    'category' => $row->category ?? null,
                    'source' => $row->source ?? null,
                    'transaction_date' => $row->transaction_date,
                    'payment_method' => $row->payment_method ?? null,
                    'bank_account_id' => $row->bank_account_id ?? null,
                    'attachment_path' => $row->attachment_path ?? null,
                    'attachment_type' => $row->attachment_type ?? null,
                    'status' => 'pending',
                    'notes' => null,
                    'recorded_by' => $row->recorded_by,
                    'approved_by' => $row->approved_by ?? null,
                    'approved_at' => $row->approved_at ?? null,
                    'created_at' => $row->created_at ?? null,
                    'updated_at' => $row->updated_at ?? null,
                    'deleted_at' => $row->deleted_at ?? null,
                ]);
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
