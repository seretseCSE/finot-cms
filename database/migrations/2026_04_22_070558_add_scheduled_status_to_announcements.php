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
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, recreate the table with the correct schema
            $this->recreateAnnouncementsTable();
        } else {
            DB::statement("ALTER TABLE announcements MODIFY status ENUM('Draft','Scheduled','Active','Expired','Archived') NOT NULL DEFAULT 'Draft'");
        }

        if (! Schema::hasColumn('announcements', 'published_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->recreateAnnouncementsTableDown();
        } else {
            DB::statement("ALTER TABLE announcements MODIFY status ENUM('Active','Expired','Archived') NOT NULL DEFAULT 'Active'");
        }

        if (Schema::hasColumn('announcements', 'published_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }
    }

    private function recreateAnnouncementsTable(): void
    {
        // Get existing data
        $existingData = DB::table('announcements')->get();

        Schema::dropIfExists('announcements');

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_am', 255)->nullable();
            $table->longText('content');
            $table->longText('content_am')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->string('status')->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_global')->default(false);
            $table->string('target_audience', 50)->nullable();
            $table->longText('broadcast_channels')->nullable();
            $table->longText('acknowledged_by')->nullable();
            $table->timestamp('broadcast_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Restore data
        foreach ($existingData as $row) {
            $data = (array) $row;
            unset($data['published_at']);
            DB::table('announcements')->insert($data);
        }
    }

    private function recreateAnnouncementsTableDown(): void
    {
        $existingData = DB::table('announcements')->get();

        Schema::dropIfExists('announcements');

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_am', 255)->nullable();
            $table->longText('content');
            $table->longText('content_am')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->string('status')->default('Active');
            $table->boolean('is_global')->default(false);
            $table->string('target_audience', 50)->nullable();
            $table->longText('broadcast_channels')->nullable();
            $table->longText('acknowledged_by')->nullable();
            $table->timestamp('broadcast_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ($existingData as $row) {
            $data = (array) $row;
            unset($data['published_at']);
            DB::table('announcements')->insert($data);
        }
    }
};
