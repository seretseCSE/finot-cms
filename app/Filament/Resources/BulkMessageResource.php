<?php

namespace App\Filament\Resources;

use App\Enums\BulkMessageStatus;
use App\Filament\Resources\BulkMessageResource\Pages;
use App\Jobs\FanOutBulkMessageJob;
use App\Models\BulkMessage;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BulkMessageResource extends BaseResource
{
    protected static ?string $model = BulkMessage::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bulk Messages';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('category_id')
                ->relationship('category', 'label_en')
                ->required(),
            Forms\Components\Textarea::make('body')->required()->maxLength(2000),
            Forms\Components\Select::make('department_id')
                ->relationship('department', 'name_en')
                ->searchable()
                ->visible(fn () => Auth::user()?->can('messages.broadcast')),
            Forms\Components\Select::make('audience.class_ids')
                ->label('Classes')
                ->multiple()
                ->options(fn () => \App\Models\ClassModel::query()->orderBy('name')->pluck('name', 'id'))
                ->dehydrated(false),
            Forms\Components\Select::make('audience.group_ids')
                ->label('Groups')
                ->multiple()
                ->options(fn () => \App\Models\Group::query()->orderBy('name')->pluck('name', 'id'))
                ->dehydrated(false),
            Forms\Components\Select::make('audience.member_ids')
                ->label('Selected students')
                ->multiple()
                ->searchable()
                ->options(fn () => \App\Models\Member::query()->orderBy('first_name')->limit(200)->get()->mapWithKeys(fn ($m) => [$m->id => $m->full_name]))
                ->dehydrated(false),
            Forms\Components\Toggle::make('confirm_global')
                ->label('Broadcast globally')
                ->visible(fn () => Auth::user()?->can('messages.broadcast_global') || Auth::user()?->hasRole('superadmin')),
            Forms\Components\Toggle::make('quiet_hours_bypassed')
                ->label('Bypass quiet hours (emergencies)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.label_en'),
                Tables\Columns\TextColumn::make('body')->limit(40),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('sender.name'),
                Tables\Columns\TextColumn::make('sent_at')->dateTime(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('send')
                    ->visible(fn (BulkMessage $record) => $record->status === BulkMessageStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (BulkMessage $record) {
                        $record->update(['status' => BulkMessageStatus::Queued]);
                        FanOutBulkMessageJob::dispatch($record->id);
                        Notification::make()->title('Message queued')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkMessages::route('/'),
            'create' => Pages\CreateBulkMessage::route('/create'),
            'edit' => Pages\EditBulkMessage::route('/{record}/edit'),
        ];
    }
}
