<?php

namespace App\Support;

/**
 * The report-card policy vocabulary: the fixed skill rating scale, the
 * suggested Ethiopian skill catalog (what the settings UI offers as one-tap
 * additions), and the shared validation rules for the school/branch settings
 * endpoints. The configured skill list itself lives in schools.settings /
 * branches.settings under `report_card_skills` — school content, so each row
 * carries its own tri-language label like chat templates do.
 */
class ReportCardSettings
{
    /** The fixed rating scale every skill row is marked on. */
    public const RATINGS = ['E', 'VG', 'S', 'NI'];

    /** Skill groups: study/work habits vs character development. */
    public const GROUPS = ['habits', 'character'];

    public const MAX_SKILLS = 30;

    /**
     * The suggested catalog: common checklist rows on Ethiopian report cards
     * (from the classic Academic/Behavioral Assessment sheet plus widely used
     * additions). Keys are stable — the settings UI adds rows by key and the
     * school may edit labels afterwards.
     *
     * @return list<array{key: string, group: string, label: array{en: string, am: string, om: string}}>
     */
    public static function suggestedSkills(): array
    {
        return [
            ['key' => 'academic_standard', 'group' => 'habits', 'label' => ['en' => 'Academic standard', 'am' => 'የትምህርት ደረጃ', 'om' => 'Sadarkaa barnootaa']],
            ['key' => 'handwriting', 'group' => 'habits', 'label' => ['en' => 'Handwriting', 'am' => 'የእጅ ጽሑፍ', 'om' => 'Barreeffama harkaa']],
            ['key' => 'does_homework', 'group' => 'habits', 'label' => ['en' => 'Does homework / classwork', 'am' => 'የቤት ሥራ / የክፍል ሥራ ይሠራል', 'om' => 'Hojii manaa / daree ni hojjeta']],
            ['key' => 'concentrates', 'group' => 'habits', 'label' => ['en' => 'Concentrates in class', 'am' => 'በክፍል ውስጥ ትኩረት ያደርጋል', 'om' => 'Daree keessatti xiyyeeffata']],
            ['key' => 'works_independently', 'group' => 'habits', 'label' => ['en' => 'Works independently', 'am' => 'በራሱ ይሠራል', 'om' => 'Ofiin hojjeta']],
            ['key' => 'completes_on_time', 'group' => 'habits', 'label' => ['en' => 'Completes work on time', 'am' => 'ሥራን በጊዜ ይጨርሳል', 'om' => 'Hojii yeroon xumura']],
            ['key' => 'follows_instructions', 'group' => 'habits', 'label' => ['en' => 'Follows directions / instructions', 'am' => 'መመሪያዎችን ይከተላል', 'om' => 'Qajeelfama hordofa']],
            ['key' => 'keeps_materials', 'group' => 'habits', 'label' => ['en' => 'Keeps materials clean and neat', 'am' => 'ቁሳቁሶችን በንጽህና ይይዛል', 'om' => 'Meeshaalee qulqullinaan qaba']],
            ['key' => 'punctuality', 'group' => 'habits', 'label' => ['en' => 'Comes to school on time', 'am' => 'በሰዓቱ ትምህርት ቤት ይገኛል', 'om' => 'Yeroon mana barumsaa dhufa']],
            ['key' => 'personal_hygiene', 'group' => 'character', 'label' => ['en' => 'Keeps personal hygiene', 'am' => 'የግል ንጽህናን ይጠብቃል', 'om' => 'Qulqullina dhuunfaa eega']],
            ['key' => 'wears_uniform', 'group' => 'character', 'label' => ['en' => 'Wears uniform regularly', 'am' => 'የደንብ ልብስ በመደበኛነት ይለብሳል', 'om' => 'Uffata seeraa yeroo hunda uffata']],
            ['key' => 'obeys_rules', 'group' => 'character', 'label' => ['en' => 'Obeys school rules', 'am' => 'የትምህርት ቤት ደንቦችን ያከብራል', 'om' => 'Seera mana barumsaa kabaja']],
            ['key' => 'polite', 'group' => 'character', 'label' => ['en' => 'Is polite', 'am' => 'ትሑት ነው', 'om' => 'Naamusa qaba']],
            ['key' => 'respects_others', 'group' => 'character', 'label' => ['en' => 'Respects teachers and elders', 'am' => 'መምህራንን እና ታላላቆችን ያከብራል', 'om' => 'Barsiisotaa fi maanguddoota kabaja']],
            ['key' => 'self_control', 'group' => 'character', 'label' => ['en' => 'Displays self-control', 'am' => 'ራስን መግዛት ያሳያል', 'om' => 'Of-to’annaa agarsiisa']],
            ['key' => 'self_confidence', 'group' => 'character', 'label' => ['en' => 'Displays self-confidence', 'am' => 'በራስ መተማመን ያሳያል', 'om' => 'Of-amanamummaa agarsiisa']],
            ['key' => 'shares', 'group' => 'character', 'label' => ['en' => 'Shares and cooperates', 'am' => 'ያካፍላል እና ይተባበራል', 'om' => 'Qooddatee walii gala']],
            ['key' => 'participates', 'group' => 'character', 'label' => ['en' => 'Participates in class activities', 'am' => 'በክፍል እንቅስቃሴዎች ይሳተፋል', 'om' => 'Sochii daree keessatti hirmaata']],
        ];
    }

    /**
     * Validation rules for the configured skill list, shared by the school
     * settings endpoint and the branch override (prefixed).
     *
     * @return array<string, mixed>
     */
    public static function skillRules(string $field = 'report_card_skills'): array
    {
        return [
            $field => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_SKILLS],
            $field.'.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            $field.'.*.group' => ['required', 'string', 'in:'.implode(',', self::GROUPS)],
            $field.'.*.label' => ['required', 'array'],
            $field.'.*.label.en' => ['required', 'string', 'max:120'],
            $field.'.*.label.am' => ['required', 'string', 'max:120'],
            $field.'.*.label.om' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * Normalize a configured list: unique keys, whitelisted groups, trimmed
     * labels. Guards the JSONB against junk regardless of who writes it.
     *
     * @param  array<int, mixed>  $skills
     * @return list<array{key: string, group: string, label: array{en: string, am: string, om: string}}>
     */
    public static function normalize(array $skills): array
    {
        $seen = [];
        $out = [];

        foreach ($skills as $skill) {
            if (! is_array($skill) || ! isset($skill['key'], $skill['label']) || isset($seen[$skill['key']])) {
                continue;
            }

            $seen[$skill['key']] = true;
            $out[] = [
                'key' => (string) $skill['key'],
                'group' => in_array($skill['group'] ?? null, self::GROUPS, true) ? $skill['group'] : 'habits',
                'label' => [
                    'en' => trim((string) ($skill['label']['en'] ?? '')),
                    'am' => trim((string) ($skill['label']['am'] ?? '')),
                    'om' => trim((string) ($skill['label']['om'] ?? '')),
                ],
            ];
        }

        return $out;
    }
}
