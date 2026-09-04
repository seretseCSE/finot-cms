<?php

namespace App\Models;

use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A school-curated preset message for staff chat (guideline tone + instant
 * tri-language parent communication). School rows (branch_id null) serve every
 * branch; branch rows add local presets. `body` = {en, am, om} with
 * placeholders resolved per conversation at pick time.
 */
#[Fillable(['school_id', 'branch_id', 'name', 'category', 'body', 'is_active', 'created_by'])]
class ChatMessageTemplate extends Model
{
    use SoftDeletes;

    /** Fixed category set — drives grouping in the picker and the studio. */
    public const CATEGORIES = [
        'general', 'attendance', 'homework', 'behavior', 'praise', 'fees', 'meeting',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The template text in a language, falling back en → first non-empty. */
    public function bodyIn(string $language): string
    {
        $body = $this->body ?? [];

        $text = trim((string) ($body[$language] ?? ''));
        if ($text !== '') {
            return $text;
        }

        $english = trim((string) ($body['en'] ?? ''));
        if ($english !== '') {
            return $english;
        }

        foreach ($body as $candidate) {
            if (trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    /**
     * Placeholder-resolved text for one conversation: {student_name} from the
     * thread's child, {teacher_name} the sender, {school_name}, {date} today
     * in the school's display calendar and the target language.
     */
    public function resolveFor(Conversation $conversation, User $sender, string $language): string
    {
        $modes = DateFormatter::modesFor(
            $conversation->school_id !== null ? (int) $conversation->school_id : null,
            $conversation->branch_id !== null ? (int) $conversation->branch_id : null,
        );

        $replacements = [
            '{student_name}' => $conversation->student?->first_name ?? '',
            '{teacher_name}' => $sender->name ?? '',
            '{school_name}' => $conversation->school?->name ?? '',
            '{date}' => DateFormatter::date(now(), $modes['calendar'], $language),
        ];

        return trim(strtr($this->bodyIn($language), $replacements));
    }
}
