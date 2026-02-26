<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParentResource\Pages;
use App\Models\ParentModel;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ParentResource extends Resource
{
    protected static ?string $model = ParentModel::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-heart'; }

    public static function getNavigationGroup(): ?string { return 'Membership'; }

    public static function getNavigationLabel(): string { return 'Parents / ወላጆች'; }

    public static function getModelLabel(): string { return 'Parent'; }

    public static function getPluralModelLabel(): string { return 'Parents'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(200),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->required()
                            ->regex('/^(\+251|0)?9\d{8}$/')
                            ->unique(ignoreRecord: true)
                            ->live(debounce: 500),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(2),
            ]);
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
                    ->formatStateUsing(fn ($state) => $state . ' children'),

                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                
                Tables\Actions\Action::make('view_linked_children')
                    ->label('View Linked Children')
                    ->icon('heroicon-o-users')
                    ->url(fn ($record) => route('filament.admin.resources.members.index', [
                        'parent_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
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
            'index' => Pages\ListParents::class,
            'create' => Pages\CreateParent::class,
            'edit' => Pages\EditParent::class,
            'view' => Pages\ViewParent::class,
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        
        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        
        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();
        
        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['hr_head', 'admin', 'superadmin'])) {
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
            'superadmin'
        ]);
    }

    protected static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->withCount('members');
    }
}

