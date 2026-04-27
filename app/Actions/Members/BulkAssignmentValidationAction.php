<?php

namespace App\Actions\Members;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Cancel;

class BulkAssignmentValidationAction
{
    public const MAX_SELECTION_COUNT = 100;

    /**
     * Validate that the selected record count does not exceed the limit.
     *
     * @throws Cancel
     */
    public static function validateSelectionLimit(BulkAction $action): void
    {
        $selectedCount = $action->getSelectedRecordsQuery()->count();

        if ($selectedCount > self::MAX_SELECTION_COUNT) {
            Notification::make()
                ->title('Selection Limit Exceeded')
                ->body("You can assign a maximum of {self::MAX_SELECTION_COUNT} members at a time.")
                ->warning()
                ->send();

            throw new Cancel();
        }
    }

    /**
     * Validate that a required field is present in the action data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validateRequiredField(array $data, string $field, string $label): bool
    {
        if (empty($data[$field])) {
            Notification::make()
                ->title('Validation Error')
                ->body("Please select a {$label}.")
                ->danger()
                ->send();

            return false;
        }

        return true;
    }
}
