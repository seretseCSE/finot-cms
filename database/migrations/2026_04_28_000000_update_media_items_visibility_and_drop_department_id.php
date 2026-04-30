<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: recreate the table since it doesn't support MODIFY or dropping columns easily
            $this->recreateMediaItemsTableForSqlite();
        } else {
            // MySQL/PostgreSQL: alter the enum and drop the column (if they still exist)

            // Step 1: Expand the enum to include all old values plus Hidden (no-op if already up to date)
            $currentEnum = DB::selectOne("SHOW COLUMNS FROM media_items WHERE Field = 'visibility'");
            if ($currentEnum && str_contains($currentEnum->Type, 'Members Only')) {
                DB::statement("ALTER TABLE media_items MODIFY visibility ENUM('Public', 'Members Only', 'Department Only', 'Hidden') NOT NULL DEFAULT 'Public'");

                // Step 2: Migrate existing non-Public visibility values to Hidden
                DB::table('media_items')
                    ->whereIn('visibility', ['Members Only', 'Department Only'])
                    ->update(['visibility' => 'Hidden']);

                // Step 3: Reduce the enum to only Public/Hidden
                DB::statement("ALTER TABLE media_items MODIFY visibility ENUM('Public', 'Hidden') NOT NULL DEFAULT 'Public'");
            }

            // Step 4: Drop the department_id foreign key and column (if they still exist)
            if (Schema::hasColumn('media_items', 'department_id')) {
                Schema::table('media_items', function (Blueprint $table) {
                    if (Schema::hasColumn('media_items', 'department_id')) {
                        $table->dropForeign(['department_id']);
                        $table->dropColumn('department_id');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->restoreMediaItemsTableForSqlite();
        } else {
            // Restore department_id column
            Schema::table('media_items', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            });

            // Restore the old enum values
            DB::statement("ALTER TABLE media_items MODIFY visibility ENUM('Public', 'Members Only', 'Department Only') NOT NULL DEFAULT 'Public'");
        }
    }

    private function recreateMediaItemsTableForSqlite(): void
    {
        // Get existing data
        $existingData = DB::table('media_items')->get();

        // Drop existing indexes
        Schema::dropIfExists('media_items');

        // Create new table without department_id and with Public/Hidden only
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->enum('type', ['Photo', 'Video'])->notNull();
            $table->foreignId('category_id')->constrained('media_categories')->onDelete('restrict');
            $table->foreignId('subcategory_id')->nullable()->constrained('media_subcategories')->onDelete('set null');
            $table->text('description')->nullable();
            $table->string('file_path', 500);
            $table->integer('file_size_kb');
            $table->string('event_album', 255)->nullable();
            $table->text('tags')->nullable();
            $table->enum('visibility', ['Public', 'Hidden'])->default('Public');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('visibility');
            $table->index('uploaded_by');
            $table->index('created_at');
        });

        // Restore data with updated visibility values
        foreach ($existingData as $row) {
            $visibility = in_array($row->visibility, ['Members Only', 'Department Only'])
                ? 'Hidden'
                : $row->visibility;

            $insertData = (array) $row;
            unset($insertData['department_id']);
            $insertData['visibility'] = $visibility;

            DB::table('media_items')->insert($insertData);
        }
    }

    private function restoreMediaItemsTableForSqlite(): void
    {
        $existingData = DB::table('media_items')->get();

        Schema::dropIfExists('media_items');

        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->enum('type', ['Photo', 'Video'])->notNull();
            $table->foreignId('category_id')->constrained('media_categories')->onDelete('restrict');
            $table->foreignId('subcategory_id')->nullable()->constrained('media_subcategories')->onDelete('set null');
            $table->text('description')->nullable();
            $table->string('file_path', 500);
            $table->integer('file_size_kb');
            $table->string('event_album', 255)->nullable();
            $table->text('tags')->nullable();
            $table->enum('visibility', ['Public', 'Members Only', 'Department Only'])->default('Public');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('visibility');
            $table->index('department_id');
            $table->index('uploaded_by');
            $table->index('created_at');
        });

        foreach ($existingData as $row) {
            $insertData = (array) $row;
            $insertData['department_id'] = null;
            DB::table('media_items')->insert($insertData);
        }
    }
};
