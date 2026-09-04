<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Platform subject catalog per the Ethiopian national curriculum framework
 * (MoE, KG–Grade 12). Codes are stable machine keys used by analytics — never
 * rename them. Each subject carries an EXPLICIT grade set (grade_level_subject
 * pivot; empty = every grade) expressed here as grade_levels.sort_order values
 * (1=KG1, 2=KG2, 3=KG3, 4=Grade 1 … 15=Grade 12); schools may add custom
 * subjects (school_id set). Idempotent — safe to re-run.
 */
class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, category, grade sort_orders (contiguous spans spelled
        // out via range() — gaps are fully supported, list any set)]
        $subjects = [
            // Pre-primary (KG1–KG3) — the MoE play-based learning areas. A KG
            // section must have a curriculum or the teaching grid, timetable
            // and marklists all come up empty for it.
            ['KGLL',  'Language & Literacy (KG)',      'language', range(1, 3)],
            ['KGEN',  'English (KG)',                  'language', range(1, 3)],
            ['KGNU',  'Numeracy (KG)',                 'mathematics', range(1, 3)],
            ['KGEV',  'Environmental Awareness (KG)',  'natural_science', range(1, 3)],
            ['KGAM',  'Arts & Music (KG)',             'arts_pe', range(1, 3)],
            ['KGPD',  'Physical Development (KG)',     'arts_pe', range(1, 3)],

            // Languages
            ['AMH',   'Amharic',                       'language', range(4, 13)],   // Grade 1–10
            ['ENG',   'English',                       'language', range(4, 15)],   // Grade 1–12
            ['AFO',   'Afan Oromo',                    'language', range(6, 13)],   // Grade 3–10
            ['TIG',   'Tigrinya',                      'language', range(4, 13)],   // regional (mirrors Amharic)
            ['SOM',   'Somali',                        'language', range(4, 13)],   // regional (mirrors Amharic)
            ['MT',    'Mother Tongue',                 'language', range(4, 13)],   // regional (mirrors Amharic)

            // Mathematics
            ['MATH',  'Mathematics',                   'mathematics', range(4, 15)], // Grade 1–12

            // Natural sciences
            ['ENV',   'Environmental Science',         'natural_science', range(4, 9)],   // Grade 1–6
            ['GSCI',  'General Science',               'natural_science', range(10, 11)],  // Grade 7–8
            ['PHY',   'Physics',                       'natural_science', range(12, 15)],  // Grade 9–12
            ['CHEM',  'Chemistry',                     'natural_science', range(12, 15)],  // Grade 9–12
            ['BIO',   'Biology',                       'natural_science', range(12, 15)],  // Grade 9–12

            // Social sciences & civics
            ['MRL',   'Moral Education',               'social_science', range(4, 9)],    // Grade 1–6
            ['SOC',   'Social Studies',                'social_science', range(10, 11)],  // Grade 7–8
            ['CIV',   'Citizenship Education',         'social_science', range(10, 13)],  // Grade 7–10
            ['HIST',  'History',                       'social_science', range(12, 15)],  // Grade 9–12
            ['GEO',   'Geography',                     'social_science', range(12, 15)],  // Grade 9–12
            ['ECON',  'Economics',                     'social_science', range(12, 15)],  // Grade 9–12

            // Technology
            ['ICT',   'Information Technology',        'technology', range(10, 15)],  // Grade 7–12
            ['WEB',   'Web Development',               'technology', range(14, 15)],  // Grade 11–12

            // Arts & physical education
            ['HPE',   'Health & Physical Education',   'arts_pe', range(4, 13)],  // Grade 1–10
            ['ART',   'Performing & Visual Arts',      'arts_pe', range(4, 11)],  // Grade 1–8

            // Vocational
            ['CTE',   'Career & Technical Education',  'vocational', range(10, 11)],  // Grade 7–8
            ['AGR',   'Agriculture',                   'vocational', range(14, 15)],  // Grade 11–12
            ['MKT',   'Marketing',                     'vocational', range(14, 15)],  // Grade 11–12
        ];

        // Cognitive load for the timetable solver: heavy subjects (5) belong
        // in morning periods; light ones (1–2) can close the day.
        $weights = [
            'MATH' => 5, 'PHY' => 5, 'CHEM' => 5,
            'BIO' => 4, 'ENG' => 4, 'GSCI' => 4, 'ECON' => 4,
            'HPE' => 1, 'ART' => 1, 'MRL' => 1,
        ];

        // Subjects that teach in a special room when the branch has one.
        $roomTypes = [
            'PHY' => 'lab', 'CHEM' => 'lab', 'BIO' => 'lab', 'GSCI' => 'lab',
            'ICT' => 'ict', 'WEB' => 'ict', 'HPE' => 'gym', 'ART' => 'art',
        ];

        $idBySort = GradeLevel::query()->pluck('id', 'sort_order');

        // Row-by-row updateOrCreate: subjects.code is a PARTIAL unique index
        // (WHERE deleted_at IS NULL), which upsert/ON CONFLICT cannot target.
        foreach ($subjects as [$code, $name, $category, $sorts]) {
            $subject = Subject::updateOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'weight' => $weights[$code] ?? 3,
                'room_type' => $roomTypes[$code] ?? null,
                'school_id' => null,
                'is_active' => true,
            ]);

            $subject->gradeLevels()->sync(
                collect($sorts)->map(fn (int $s) => $idBySort->get($s))->filter()->values()->all(),
            );
        }
    }
}
