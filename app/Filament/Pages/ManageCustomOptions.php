<?php

namespace App\Filament\Pages;

use App\Models\CustomOption;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManageCustomOptions extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationLabel(): string { return 'Custom Options'; }

    public static function getNavigationSort(): ?int { return 20; }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public function getView(): string
    {
        return 'filament.pages.manage-custom-options';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('field_name')
            ->reorderable('display_order')
            ->columns([
                TextColumn::make('field_name')
                    ->label('Field Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('option_value')
                    ->label('Option Value')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                TextColumn::make('addedBy.name')
                    ->label('Added By')
                    ->toggleable(),

                TextColumn::make('added_at')
                    ->label('Added Date')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('usage_count')
                    ->label('Usage Count')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('field_name')
                    ->label('Field Name')
                    ->options(fn () => CustomOption::query()->select('field_name')->distinct()->orderBy('field_name')->pluck('field_name', 'field_name')->all())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'pending',
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CustomOption $record): bool => $record->status === 'pending')
                    ->action(function (CustomOption $record): void {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CustomOption $record): bool => $record->status === 'pending')
                    ->action(function (CustomOption $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),

                Action::make('merge')
                    ->label('Merge')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn (CustomOption $record): bool => in_array($record->status, ['approved', 'pending'], true))
                    ->form(function (CustomOption $record): array {
                        $targets = CustomOption::query()
                            ->where('field_name', $record->field_name)
                            ->whereIn('status', ['approved', 'pending'])
                            ->whereKeyNot($record->getKey())
                            ->orderBy('option_value')
                            ->pluck('option_value', 'id')
                            ->all();

                        return [
                            Forms\Components\Select::make('target_id')
                                ->label('Target Option')
                                ->options($targets)
                                ->searchable()
                                ->preload()
                                ->required(),
                        ];
                    })
                    ->action(function (CustomOption $record, array $data): void {
                        $target = CustomOption::query()->findOrFail($data['target_id']);

                        DB::transaction(function () use ($record, $target): void {
                            $this->mergeOptionValueIntoTarget($record->field_name, $record->option_value, $target->option_value);

                            $target->update([
                                'usage_count' => $target->usage_count + $record->usage_count,
                            ]);

                            $record->delete();
                        });

                        Notification::make()
                            ->title('Merged successfully')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CustomOption $record): bool => $record->status === 'approved' && (int) $record->usage_count === 0)
                    ->action(fn (CustomOption $record) => $record->delete()),
            ])
            ->bulkActions([
                BulkAction::make('approve_selected')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (array $records): void {
                        CustomOption::query()
                            ->whereIn('id', collect($records)->pluck('id'))
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'approved',
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                            ]);
                    }),

                BulkAction::make('reject_selected')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (array $records): void {
                        CustomOption::query()
                            ->whereIn('id', collect($records)->pluck('id'))
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'rejected',
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                            ]);
                    }),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return CustomOption::query()->with(['addedBy', 'approvedBy']);
    }

    protected function mergeOptionValueIntoTarget(string $fieldName, string $fromValue, string $toValue): void
    {
        $map = [
            'employment_status' => ['table' => 'members', 'column' => 'employment_status'],
            'payment_method' => ['table' => 'contributions', 'column' => 'payment_method'],
            'donation_type' => ['table' => 'donations', 'column' => 'donation_type'],
            'relationship' => ['table' => 'member_parent_guardians', 'column' => 'relationship'],
            'removal_reason' => ['table' => 'members', 'column' => 'removal_reason'],
            'inventory_category' => ['table' => 'inventory_items', 'column' => 'inventory_category'],
            'inventory_unit' => ['table' => 'inventory_items', 'column' => 'inventory_unit'],
        ];

        $target = $map[$fieldName] ?? null;

        if (! $target) {
            return;
        }

        if (! Schema::hasTable($target['table'])) {
            return;
        }

        if (! Schema::hasColumn($target['table'], $target['column'])) {
            return;
        }

        DB::table($target['table'])
            ->where($target['column'], $fromValue)
            ->update([$target['column'] => $toValue]);
    }
}
