<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('student_enrollments', 'removed_at')) {
                $table->timestamp('removed_at')->nullable()->after('withdrawal_notes');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('department_id')->constrained('members')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'tour_version')) {
                $table->string('tour_version', 20)->nullable()->after('language_preference');
            }
        });

        Schema::table('classes', function (Blueprint $table) {
            if (! Schema::hasColumn('classes', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->nullable()->after('created_by');
            }
        });

        Schema::table('rehearsals', function (Blueprint $table) {
            if (! Schema::hasColumn('rehearsals', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->nullable();
            }
            if (! Schema::hasColumn('rehearsals', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }
        });

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('in_app_notifications')) {
            Schema::create('in_app_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('event');
                $table->string('category');
                $table->json('data')->nullable();
                $table->string('link')->nullable();
                $table->string('dedupe_key')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('terms')) {
            Schema::create('terms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
                $table->unique(['academic_year_id', 'name']);
            });
        }

        if (! Schema::hasTable('marklists')) {
            Schema::create('marklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->string('status')->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assisted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assisted_at')->nullable();
                $table->string('assist_reason')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->unique(['class_id', 'term_id', 'subject_id']);
            });
        }

        if (! Schema::hasTable('marklist_items')) {
            Schema::create('marklist_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marklist_id')->constrained('marklists')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->string('conduct')->nullable();
                $table->string('memorization')->nullable();
                $table->string('participation')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['marklist_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('member_imports')) {
            Schema::create('member_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->string('file_name');
                $table->string('status')->default('draft');
                $table->json('column_map')->nullable();
                $table->json('options')->nullable();
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('member_import_rows')) {
            Schema::create('member_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_import_id')->constrained('member_imports')->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->json('data');
                $table->string('status')->default('ready');
                $table->json('issues')->nullable();
                $table->foreignId('duplicate_member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->string('resolution')->nullable();
                $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->unique(['member_import_id', 'row_number']);
            });
        }

        if (! Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
                $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users');
                $table->text('reason');
                $table->string('destination')->nullable();
                $table->dateTime('requested_at');
                $table->string('status')->default('pending');
                $table->foreignId('education_decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('education_decided_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->date('effective_date')->nullable();
                $table->boolean('guardian_acknowledged')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facilities')) {
            Schema::create('facilities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type')->default('other');
                $table->unsignedInteger('capacity')->nullable();
                $table->text('location_notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('booked_by')->constrained('users');
                $table->string('purpose');
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->string('status')->default('pending');
                $table->string('recurrence_rule')->nullable();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('rehearsal_id')->nullable()->constrained('rehearsals')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('message_categories')) {
            Schema::create('message_categories', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('label_en');
                $table->string('label_am');
                $table->boolean('sms_allowed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bulk_messages')) {
            Schema::create('bulk_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sender_id')->constrained('users');
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('category_id')->constrained('message_categories');
                $table->text('body');
                $table->json('channels')->nullable();
                $table->string('status')->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->boolean('quiet_hours_bypassed')->default(false);
                $table->boolean('confirm_global')->default(false);
                $table->json('audience')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bulk_message_recipients')) {
            Schema::create('bulk_message_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bulk_message_id')->constrained('bulk_messages')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('channel')->default('in_app');
                $table->string('status')->default('pending');
                $table->timestamp('sent_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
                $table->unique(['bulk_message_id', 'member_id', 'channel']);
            });
        }

        if (! Schema::hasTable('page_views')) {
            Schema::create('page_views', function (Blueprint $table) {
                $table->id();
                $table->string('path');
                $table->string('referrer')->nullable();
                $table->string('session_hash', 64);
                $table->dateTime('created_at');
                $table->index(['created_at', 'path']);
            });
        }

        if (Schema::hasTable('classes') && Schema::hasTable('facilities')) {
            try {
                Schema::table('classes', function (Blueprint $table) {
                    $table->foreign('facility_id')->references('id')->on('facilities')->nullOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('rehearsals') && Schema::hasTable('facilities')) {
            try {
                Schema::table('rehearsals', function (Blueprint $table) {
                    $table->foreign('facility_id')->references('id')->on('facilities')->nullOnDelete();
                });
            } catch (\Throwable $e) {
            }
        }

        if (! DB::table('platform_settings')->where('key', 'notifications.sms_whitelist')->exists()) {
            DB::table('platform_settings')->insert([
                'key' => 'notifications.sms_whitelist',
                'value' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $now = now();
        foreach ([
            ['announcement', 'Announcement', 'ማስታወቂያ', 1],
            ['reminder', 'Reminder', 'አስታዋሽ', 2],
            ['event_invite', 'Event invite', 'የዝግጅት ግብዣ', 3],
            ['emergency', 'Emergency', 'አስቸኳይ', 4],
        ] as $row) {
            if (DB::table('message_categories')->where('key', $row[0])->exists()) {
                continue;
            }

            DB::table('message_categories')->insert([
                'key' => $row[0],
                'label_en' => $row[1],
                'label_am' => $row[2],
                'sms_allowed' => false,
                'is_active' => true,
                'sort_order' => $row[3],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('bulk_message_recipients');
        Schema::dropIfExists('bulk_messages');
        Schema::dropIfExists('message_categories');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('member_import_rows');
        Schema::dropIfExists('member_imports');
        Schema::dropIfExists('marklist_items');
        Schema::dropIfExists('marklists');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('platform_settings');
    }
};
