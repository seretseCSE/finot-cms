<?php

namespace App\Filament\Resources;

use App\Enums\MemberImportStatus;
use App\Filament\Resources\MemberImportResource\Pages;
use App\Jobs\CommitMemberImportJob;
use App\Models\MemberImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MemberImportResource extends BaseResource
{
    protected static ?string $model = MemberImport::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'Member Import';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('academic_year_id')
                ->relationship('academicYear', 'name')
                ->required(),
            Forms\Components\Select::make('class_id')
                ->relationship('class', 'name')
                ->searchable(),
            Forms\Components\TextInput::make('file_name')->required()->maxLength(255),
            Forms\Components\Textarea::make('csv')
                ->label('CSV (header row + data)')
                ->rows(12)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('imported_count'),
                Tables\Columns\TextColumn::make('skipped_count'),
                Tables\Columns\TextColumn::make('failed_count'),
                Tables\Columns\TextColumn::make('createdBy.name'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('commit')
                    ->visible(fn (MemberImport $record) => $record->status === MemberImportStatus::Draft
                        && Auth::user()?->can('imports.commit'))
                    ->requiresConfirmation()
                    ->action(function (MemberImport $record) {
                        CommitMemberImportJob::dispatch($record->id, Auth::id());
                        Notification::make()->title('Import queued')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemberImports::route('/'),
            'create' => Pages\CreateMemberImport::route('/create'),
            'edit' => Pages\EditMemberImport::route('/{record}/edit'),
        ];
    }
}
