<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            if (! Schema::hasColumn('classes', 'program_year')) {
                $table->unsignedTinyInteger('program_year')->nullable();
                $table->index('program_year');
            }
        });

        Schema::table('terms', function (Blueprint $table) {
            if (! Schema::hasColumn('terms', 'semester_number')) {
                $table->unsignedTinyInteger('semester_number')->nullable();
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('subjects', 'program_year')) {
                $table->unsignedTinyInteger('program_year')->nullable();
            }
            if (! Schema::hasColumn('subjects', 'semester_number')) {
                $table->unsignedTinyInteger('semester_number')->nullable();
            }
            if (! Schema::hasColumn('subjects', 'max_score')) {
                $table->unsignedSmallInteger('max_score')->default(100);
            }
        });

        Schema::table('marklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('marklist_items', 'score')) {
                $table->decimal('score', 6, 2)->nullable();
            }
            if (! Schema::hasColumn('marklist_items', 'max_score')) {
                $table->unsignedSmallInteger('max_score')->nullable();
            }
            if (! Schema::hasColumn('marklist_items', 'rank')) {
                $table->unsignedSmallInteger('rank')->nullable();
            }
        });

        if (! Schema::hasTable('grading_scales')) {
            Schema::create('grading_scales', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grading_scale_bands')) {
            Schema::create('grading_scale_bands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
                $table->string('label', 16);
                $table->unsignedSmallInteger('min_score');
                $table->unsignedSmallInteger('max_score');
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $this->renameGradeClasses();
        $this->seedDefaultGradingScale();
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scale_bands');
        Schema::dropIfExists('grading_scales');

        Schema::table('marklist_items', function (Blueprint $table) {
            foreach (['score', 'max_score', 'rank'] as $column) {
                if (Schema::hasColumn('marklist_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('subjects', function (Blueprint $table) {
            foreach (['program_year', 'semester_number', 'max_score'] as $column) {
                if (Schema::hasColumn('subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('terms', function (Blueprint $table) {
            if (Schema::hasColumn('terms', 'semester_number')) {
                $table->dropColumn('semester_number');
            }
        });

        Schema::table('classes', function (Blueprint $table) {
            if (Schema::hasColumn('classes', 'program_year')) {
                $table->dropColumn('program_year');
            }
        });
    }

    protected function renameGradeClasses(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        $classes = DB::table('classes')->orderBy('id')->get();

        foreach ($classes as $class) {
            $name = (string) $class->name;
            $year = null;

            if (preg_match('/^Grade\s+(\d+)$/i', $name, $matches)) {
                $n = (int) $matches[1];
                $year = min(max($n, 1), 5);
                $newName = $n <= 5 ? "Year {$n}" : "Year {$year} (former Grade {$n})";
                $payload = ['name' => $newName, 'program_year' => $year];
                if ($n > 5) {
                    $payload['is_active'] = false;
                    $payload['description'] = trim(($class->description ? $class->description.' ' : '').'Archived K-12 grade; mapped to a university program year.');
                }
                DB::table('classes')->where('id', $class->id)->update($payload);
            } elseif (preg_match('/^Year\s+(\d+)/i', $name, $matches)) {
                DB::table('classes')->where('id', $class->id)->update([
                    'program_year' => min(max((int) $matches[1], 1), 5),
                ]);
            }
        }
    }

    protected function seedDefaultGradingScale(): void
    {
        if (DB::table('grading_scales')->exists()) {
            return;
        }

        $scaleId = DB::table('grading_scales')->insertGetId([
            'name' => 'Default university scale',
            'is_default' => true,
            'created_by' => DB::table('users')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bands = [
            ['A+', 90, 100, 1],
            ['A', 85, 89, 2],
            ['A-', 80, 84, 3],
            ['B+', 75, 79, 4],
            ['B', 70, 74, 5],
            ['B-', 65, 69, 6],
            ['C+', 60, 64, 7],
            ['C', 50, 59, 8],
            ['D', 40, 49, 9],
            ['F', 0, 39, 10],
        ];

        foreach ($bands as [$label, $min, $max, $order]) {
            DB::table('grading_scale_bands')->insert([
                'grading_scale_id' => $scaleId,
                'label' => $label,
                'min_score' => $min,
                'max_score' => $max,
                'sort_order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
