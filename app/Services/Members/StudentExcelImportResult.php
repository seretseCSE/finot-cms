<?php

namespace App\Services\Members;

final class StudentExcelImportResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $imported = 0,
        public int $failed = 0,
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    public function title(): string
    {
        if ($this->imported > 0 && $this->failed === 0) {
            return 'Students imported';
        }

        if ($this->imported > 0) {
            return 'Students imported with some errors';
        }

        if ($this->failed > 0) {
            return 'Import failed';
        }

        return 'No students imported';
    }

    public function body(): string
    {
        $parts = ["Imported {$this->imported} student(s)."];

        if ($this->failed > 0) {
            $parts[] = "{$this->failed} row(s) failed.";
        }

        $notes = array_slice([...$this->errors, ...$this->warnings], 0, 8);

        if ($notes !== []) {
            $parts[] = implode("\n", $notes);
        }

        $remaining = (count($this->errors) + count($this->warnings)) - count($notes);
        if ($remaining > 0) {
            $parts[] = "…and {$remaining} more.";
        }

        return implode("\n", $parts);
    }

    public function isSuccess(): bool
    {
        return $this->imported > 0 && $this->failed === 0;
    }

    public function isWarning(): bool
    {
        return $this->imported > 0 && $this->failed > 0;
    }
}
