<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParentResource\Pages;
use App\Exports\ParentExport;
use App\Jobs\ProcessExportJob;
use App\Models\ParentModel;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ParentResource extends Resource
{
    protected static ?string $model = ParentModel::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-heart';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Membership Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Parents';
    }

    public static function getModelLabel(): string
    {
        return 'Parent';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Parents';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('member_count')
                    ->label('Linked Children')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state.' children'),

                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),

                Action::make('view_linked_children')
                    ->label('View Linked Children')
                    ->icon('heroicon-o-users')
                    ->url(fn ($record) => route('filament.admin.resources.members.index', [
                        'parent_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),

                BulkAction::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        CheckboxList::make('columns')
                            ->label('Columns')
                            ->options(ParentExport::availableColumns())
                            ->default(array_keys(ParentExport::availableColumns()))
                            ->columns(2)
                            ->required(),
                        Radio::make('format')
                            ->label('Format')
                            ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                            ->default('xlsx')
                            ->required(),
                    ])
                    ->action(function (array $data, BulkAction $action) {
                        $ids = $action->getSelectedRecords()->pluck('id')->toArray();

                        ProcessExportJob::dispatch(
                            exportClass: ParentExport::class,
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
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParents::route('/'),
            'create' => Pages\CreateParent::route('/create'),
            'edit' => Pages\EditParent::route('/{record}/edit'),
            'view' => Pages\ViewParent::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin',
        ]);
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin',
        ]);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin',
        ]);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        if (! $user->hasRole(['hr_head', 'admin', 'superadmin'])) {
            return false;
        }

        // Cannot delete if linked to any active member
        return $record->canBeDeleted();
    }

    public static function canRestore($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin',
        ]);
    }

    protected static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->withCount('members');
    }
}
