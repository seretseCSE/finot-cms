<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

abstract class BaseExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $columns;
    protected ?array $ids;
    protected ?array $filters;

    /**
     * Return an associative array of available columns: ['key' => 'Label', ...]
     */
    abstract public static function availableColumns(): array;

    /**
     * Return the model class FQCN (e.g. Member::class)
     */
    abstract public static function modelClass(): string;

    /**
     * Return the resource type string used for audit logging
     */
    abstract public static function resourceType(): string;

    /**
     * Return relationships to eager-load
     */
    abstract public static function relationships(): array;

    /**
     * Resolve a single column value from a record
     */
    abstract protected function resolveColumn($record, string $column): mixed;

    /**
     * Build the base query for this export.
     * Override in subclasses when custom filtering is needed.
     */
    protected function buildQuery(): Builder
    {
        return static::modelClass()::query();
    }

    public function __construct(array $columns = [], ?array $ids = null, ?array $filters = null)
    {
        $this->columns = $columns ?: array_keys(static::availableColumns());
        $this->ids = $ids;
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = $this->buildQuery();

        if ($this->ids !== null) {
            $query->whereIn('id', $this->ids);
        }

        if (! empty(static::relationships())) {
            $query->with(static::relationships());
        }

        return $query->get();
    }

    public function headings(): array
    {
        $available = static::availableColumns();

        return array_values(array_intersect_key($available, array_flip($this->columns)));
    }

    public function map($record): array
    {
        $row = [];
        foreach ($this->columns as $column) {
            $row[] = $this->resolveColumn($record, $column);
        }

        return $row;
    }
}
