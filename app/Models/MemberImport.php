<?php

namespace App\Models;

use App\Enums\MemberImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberImport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'academic_year_id', 'class_id', 'file_name', 'status', 'column_map', 'options',
        'total_count', 'imported_count', 'skipped_count', 'failed_count',
        'created_by', 'department_id', 'committed_at', 'finished_at',
    ];

    protected $casts = [
        'status' => MemberImportStatus::class,
        'column_map' => 'array',
        'options' => 'array',
        'committed_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function getPermissionName(string $action): string
    {
        return 'imports.commit';
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MemberImportRow::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}
