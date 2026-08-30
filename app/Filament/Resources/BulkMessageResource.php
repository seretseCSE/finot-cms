<?php

namespace App\Filament\Resources;

use App\Enums\BulkMessageStatus;
use App\Enums\MemberType;
use App\Filament\Resources\BulkMessageResource\Pages;
use App\Jobs\FanOutBulkMessageJob;
use App\Models\BulkMessage;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\RoleGate;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
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
        $canFilterAll = fn (): bool => RoleGate::canBroadcastGlobal();

        return $schema->components([
            Section::make('Message')
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->relationship('category', 'label_en')
                        ->required(),
                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->maxLength(2000)
                        ->rows(5),
                    Forms\Components\Toggle::make('quiet_hours_bypassed')
                        ->label('Bypass quiet hours (emergencies)'),
                ]),
            Section::make('Audience')
                ->description(fn () => RoleGate::canBroadcastGlobal()
                    ? 'Filter any members by department, group, class, type, or name. Leave filters empty to reach everyone.'
                    : 'This message goes to active members in your department. Optionally narrow the list.')
                ->schema([
                    Forms\Components\Toggle::make('confirm_global')
                        ->label('Broadcast to all members')
                        ->helperText('Ignore department scope and send to every matching member.')
                        ->visible($canFilterAll)
                        ->live(),
                    Forms\Components\Select::make('department_id')
                        ->relationship('department', 'name_en')
                        ->searchable()
                        ->preload()
                        ->visible($canFilterAll)
                        ->helperText('Optional. Leave empty to include every department.'),
                    Forms\Components\Select::make('audience.member_types')
                        ->label('Member type')
                        ->multiple()
                        ->options(MemberType::getAll())
                        ->visible($canFilterAll)
                        ->dehydrated(false),
                    Forms\Components\Select::make('audience.class_ids')
                        ->label('Classes')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => ClassModel::query()->orderBy('name')->pluck('name', 'id'))
                        ->visible($canFilterAll)
                        ->dehydrated(false),
                    Forms\Components\Select::make('audience.group_ids')
                        ->label('Member groups')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return MemberGroup::query()->orderBy('name')->pluck('name', 'id');
                        })
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('audience.search')
                        ->label('Name or member code')
                        ->placeholder('Search by first name, father name, or code')
                        ->visible($canFilterAll)
                        ->dehydrated(false),
                    Forms\Components\Select::make('audience.member_ids')
                        ->label('Specific members')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            $query = Member::query()->where('status', 'Active');

                            if (! RoleGate::canBroadcastGlobal() && Auth::user()?->department_id) {
                                $query->where('department_id', Auth::user()->department_id);
                            }

                            $like = '%'.$search.'%';

                            return $query
                                ->where(function ($q) use ($like) {
                                    $q->where('first_name', 'like', $like)
                                        ->orWhere('father_name', 'like', $like)
                                        ->orWhere('grandfather_name', 'like', $like)
                                        ->orWhere('member_code', 'like', $like);
                                })
                                ->orderBy('first_name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Member $member) => [$member->id => $member->full_name]);
                        })
                        ->getOptionLabelsUsing(function (array $values) {
                            return Member::query()
                                ->whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn (Member $member) => [$member->id => $member->full_name]);
                        })
                        ->dehydrated(false),
                ]),
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
