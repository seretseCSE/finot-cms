<?php

namespace App\Filament\Resources\DuplicateRecords;

use App\Filament\Resources\DuplicateRecords\Pages\CreateDuplicateRecord;
use Filament\Schemas\Schema;
use App\Filament\Resources\DuplicateRecords\Pages\EditDuplicateRecord;
use App\Filament\Resources\DuplicateRecords\Pages\ListDuplicateRecords;
use App\Models\DuplicateRecord;
use App\Services\DuplicateMergeService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DuplicateRecordResource extends BaseResource
{
    protected static ?string $model = DuplicateRecord::class;

    private static function canAdminAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('duplicate_records.view');
    }

    public static function canViewAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canCreate(): bool
    {
        return self::canAdminAccess();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canDeleteAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canForceDeleteAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function canReorder(): bool
    {
        return self::canAdminAccess();
    }

    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return self::canAdminAccess();
    }

    public static function canRestoreAny(): bool
    {
        return self::canAdminAccess();
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-duplicate';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Data Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Merge Duplicates';
    }

    public static function getModelLabel(): string
    {
        return 'Duplicate Record';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('model_type')
                    ->disabled(),

                TextInput::make('primary_record_id')
                    ->numeric()
                    ->disabled(),

                TextInput::make('duplicate_record_id')
                    ->numeric()
                    ->disabled(),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'merged' => 'Merged',
                        'ignored' => 'Ignored',
                    ])
                    ->disabled(),

                Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge(),

                Tables\Columns\TextColumn::make('primary_record_id')
                    ->label('Primary ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duplicate_record_id')
                    ->label('Duplicate ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('match_criteria')
                    ->label('Match Criteria')
                    ->formatStateUsing(fn (?array $state): string => $state ? json_encode($state) : '-')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'pending' => 'warning',
                        'merged' => 'success',
                        'ignored' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('mergedBy.name')
                    ->label('Merged By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('merged_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'merged' => 'Merged',
                        'ignored' => 'Ignored',
                    ]),

                Tables\Filters\SelectFilter::make('model_type')
                    ->options([
                        'App\Models\Member' => 'Member',
                    ]),
            ])
            ->headerActions([
                Action::make('detect_duplicates')
                    ->label('Detect Duplicates')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('primary')
                    ->action(function (): void {
                        $service = new DuplicateMergeService();
                        $duplicates = $service->findDuplicateMembers();
                        $count = $service->storeDetectedDuplicates($duplicates);

                        Notification::make()
                            ->title('Detection Complete')
                            ->body("{$count} new duplicate records detected.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Detect Duplicate Members')
                    ->modalDescription('This will scan members for potential duplicates based on phone and name combinations.'),
            ])
            ->actions([
                Action::make('merge')
                    ->label('Merge')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('success')
                    ->visible(fn (DuplicateRecord $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Merge Duplicate')
                    ->modalDescription('This will merge the duplicate record into the primary record. Related data will be transferred. This action cannot be undone.')
                    ->action(function (DuplicateRecord $record): void {
                        try {
                            $service = new DuplicateMergeService();
                            $service->mergeMember($record->id, Auth::id());

                            Notification::make()
                                ->title('Merged Successfully')
                                ->body('Duplicate record has been merged into the primary record.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Merge Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('ignore')
                    ->label('Ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (DuplicateRecord $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (DuplicateRecord $record): void {
                        $record->markAsIgnored();

                        Notification::make()
                            ->title('Ignored')
                            ->body('Duplicate record has been marked as ignored.')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('ignore_selected')
                    ->label('Ignore Selected')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->action(function (array $records): void {
                        foreach ($records as $record) {
                            if ($record->status === 'pending') {
                                $record->markAsIgnored();
                            }
                        }

                        Notification::make()
                            ->title('Ignored')
                            ->body('Selected duplicates have been marked as ignored.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDuplicateRecords::route('/'),
            'create' => CreateDuplicateRecord::route('/create'),
            'edit' => EditDuplicateRecord::route('/{record}/edit'),
        ];
    }
}
