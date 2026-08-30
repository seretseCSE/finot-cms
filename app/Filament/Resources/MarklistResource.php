<?php

namespace App\Filament\Resources;

use App\Enums\MarklistStatus;
use App\Filament\Resources\MarklistResource\Pages;
use App\Models\Marklist;
use App\Services\Academics\MarklistService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MarklistResource extends BaseResource
{
    protected static ?string $model = Marklist::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance & Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 13;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Marklists';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Textarea::make('remarks')->maxLength(2000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('class.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('term.name')->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('assistedBy.name')->label('Assisted by'),
            ])
            ->actions([
                Actions\Action::make('approve')
                    ->visible(fn (Marklist $record) => $record->status === MarklistStatus::Submitted
                        && Auth::user()?->can('results.approve'))
                    ->requiresConfirmation()
                    ->action(function (Marklist $record) {
                        app(MarklistService::class)->approve($record, Auth::user());
                        Notification::make()->title('Approved')->success()->send();
                    }),
                Actions\Action::make('reopen')
                    ->visible(fn (Marklist $record) => $record->status !== MarklistStatus::Draft
                        && Auth::user()?->can('results.approve'))
                    ->form([
                        Forms\Components\Textarea::make('remarks')->required()->minLength(10),
                    ])
                    ->action(function (Marklist $record, array $data) {
                        app(MarklistService::class)->reopen($record, Auth::user(), $data['remarks']);
                        Notification::make()->title('Reopened')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarklists::route('/'),
        ];
    }
}
