<?php

namespace App\Filament\Actions;

use App\Exports\MemberExport;
use App\Jobs\BulkAssignToDepartmentJob;
use App\Jobs\BulkAssignToGroupJob;
use App\Jobs\ProcessExportJob;
use App\Actions\Members\BulkAssignmentValidationAction;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MemberBulkActions
{
    /**
     * Get all bulk actions for MemberResource.
     *
     * @return array The bulk actions
     */
    public static function getActions(): array
    {
        return [
            DeleteBulkAction::make(),
            self::exportAction(),
            self::assignToGroupAction(),
            self::assignToDepartmentAction(),
        ];
    }

    /**
     * Export bulk action.
     *
     * @return BulkAction The export action
     */
    private static function exportAction(): BulkAction
    {
        return BulkAction::make('export')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Forms\Components\CheckboxList::make('columns')
                    ->label('Columns')
                    ->options(MemberExport::availableColumns())
                    ->default(array_keys(MemberExport::availableColumns()))
                    ->columns(2)
                    ->required(),
                Forms\Components\Radio::make('format')
                    ->label('Format')
                    ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                    ->default('xlsx')
                    ->required(),
            ])
            ->action(function (array $data, BulkAction $action) {
                $ids = $action->getSelectedRecords()->pluck('id')->toArray();

                ProcessExportJob::dispatch(
                    exportClass: MemberExport::class,
                    columns: $data['columns'],
                    format: $data['format'],
                    userId: auth()->id(),
                    ids: $ids,
                );

                Notification::make()
                    ->title('Export queued')
                    ->body('Your export is being processed. You will be notified when it is ready.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Assign to group bulk action.
     *
     * @return BulkAction The assign to group action
     */
    private static function assignToGroupAction(): BulkAction
    {
        return BulkAction::make('assign_to_group')
            ->label('Assign to Group')
            ->icon('heroicon-o-user-plus')
            ->deselectRecordsAfterCompletion()
            ->mountUsing(fn (BulkAction $action) => BulkAssignmentValidationAction::validateSelectionLimit($action))
            ->form([
                Forms\Components\Select::make('assigned_group_id')
                    ->label('Group')
                    ->options(fn () => \App\Models\MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->required(),

                Forms\Components\DatePicker::make('effective_from')
                    ->label('Effective From Date')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (BulkAction $action, array $data): void {
                if (! BulkAssignmentValidationAction::validateRequiredField($data, 'assigned_group_id', 'group')) {
                    $action->halt();
                }

                $memberIds = $action->getSelectedRecords()->pluck('id')->toArray();

                BulkAssignToGroupJob::dispatch(
                    $memberIds,
                    $data['assigned_group_id'],
                    $data['effective_from'],
                    auth()->id()
                );

                Notification::make()
                    ->title('Assignment queued')
                    ->body('The group assignment is being processed in the background.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Assign to department bulk action.
     *
     * @return BulkAction The assign to department action
     */
    private static function assignToDepartmentAction(): BulkAction
    {
        return BulkAction::make('assign_to_department')
            ->label('Assign to Department')
            ->icon('heroicon-o-building-office')
            ->deselectRecordsAfterCompletion()
            ->visible(fn (): bool => Auth::user()?->can('members.manage') ?? false)
            ->mountUsing(fn (BulkAction $action) => BulkAssignmentValidationAction::validateSelectionLimit($action))
            ->form([
                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->options(function () {
                        try {
                            $departments = \App\Models\Department::query()
                                ->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                                ->where('is_active', true)
                                ->orderBy('name_en')
                                ->pluck('name_en', 'id')
                                ->toArray();

                            return $departments;
                        } catch (\Exception $e) {
                            return [];
                        }
                    })
                    ->required()
                    ->helperText('Select department to assign selected members to'),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Assignment')
                    ->rows(2)
                    ->helperText('Optional: Provide a reason for this department assignment'),
            ])
            ->action(function (BulkAction $action, array $data): void {
                if (! BulkAssignmentValidationAction::validateRequiredField($data, 'department_id', 'department')) {
                    $action->halt();
                }

                $memberIds = $action->getSelectedRecords()->pluck('id')->toArray();

                BulkAssignToDepartmentJob::dispatch(
                    $memberIds,
                    $data['department_id'],
                    $data['reason'] ?? null,
                    auth()->id()
                );

                Notification::make()
                    ->title('Assignment queued')
                    ->body('The department assignment is being processed in the background.')
                    ->success()
                    ->send();
            });
    }
}
