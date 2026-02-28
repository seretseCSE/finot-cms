<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-megaphone'; }

    public static function getNavigationLabel(): string { return 'Announcements'; }

    public static function getNavigationGroup(): ?string { return 'Worship & Media'; }

    public static function getNavigationSort(): ?int { return 5; }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['av_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['av_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['av_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['av_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title (English)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('title_am')
                            ->label('Title (Amharic)')
                            ->maxLength(255),

                        Forms\Components\RichEditor::make('content')
                            ->label('Content (English)')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('content_am')
                            ->label('Content (Amharic)')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Scheduling')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->helperText('Leave empty for ongoing announcement')
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Active' => 'Active',
                                'Expired' => 'Expired',
                                'Archived' => 'Archived',
                            ])
                            ->default('Draft')
                            ->required(),

                        Forms\Components\Toggle::make('is_urgent')
                            ->label('Is Urgent')
                            ->default(false)
                            ->helperText('Urgent announcements will have red border and be pinned to top'),
                    ]),

                Forms\Components\Section::make('Global Announcement Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_global')
                            ->label('Global Announcement')
                            ->default(false)
                            ->helperText('Global announcements will be broadcast system-wide and require acknowledgment')
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => 
                                $state ? $set('target_audience', 'all_users') : null
                            ),

                        Forms\Components\Select::make('target_audience')
                            ->label('Target Audience')
                            ->options(Announcement::getTargetAudienceOptions())
                            ->default('all_users')
                            ->required(fn (callable $get) => $get('is_global'))
                            ->visible(fn (callable $get) => $get('is_global')),

                        Forms\Components\CheckboxList::make('broadcast_channels')
                            ->label('Broadcast Channels')
                            ->options(Announcement::getBroadcastChannelOptions())
                            ->default(['in_app'])
                            ->required(fn (callable $get) => $get('is_global'))
                            ->visible(fn (callable $get) => $get('is_global'))
                            ->helperText('Select how this announcement should be delivered'),
                    ])
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-globe-alt')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),

                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgent')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('target_audience')
                    ->label('Target Audience')
                    ->formatStateUsing(fn ($state) => Announcement::getTargetAudienceOptions()[$state] ?? $state)
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),

                Tables\Columns\TextColumn::make('ethiopian_start_date')
                    ->label('Start Date')
                    ->sortable()
                    ->date(),

                Tables\Columns\TextColumn::make('ethiopian_end_date')
                    ->label('End Date')
                    ->formatStateUsing(fn ($record) => $record->ethiopian_end_date ?: 'Ongoing')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Active',
                        'Expired' => 'Expired',
                        'Archived' => 'Archived',
                    ]),

                Tables\Filters\TernaryFilter::make('is_global')
                    ->label('Global Announcements')
                    ->placeholder('All announcements')
                    ->trueLabel('Global only')
                    ->falseLabel('Regular only')
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),

                Tables\Filters\SelectFilter::make('target_audience')
                    ->label('Target Audience')
                    ->options(Announcement::getTargetAudienceOptions())
                    ->visible(fn () => Auth::user()?->hasRole(['admin', 'superadmin'])),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),

                Tables\Filters\TernaryFilter::make('is_urgent')
                    ->label('Urgent')
                    ->placeholder('All')
                    ->trueLabel('Urgent Only')
                    ->falseLabel('Non-Urgent Only')
                    ->queries(
                        true: fn ($query) => $query->where('is_urgent', true),
                        false: fn ($query) => $query->where('is_urgent', false),
                    ),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),

                // Broadcast Global Announcement
                Tables\Actions\Action::make('broadcast')
                    ->label('Broadcast')
                    ->icon('heroicon-o-broadcast')
                    ->color('success')
                    ->visible(fn (Announcement $record): bool => 
                        $record->is_global && 
                        $record->status === 'Active' && 
                        Auth::user()->hasRole(['admin', 'superadmin'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Broadcast Global Announcement')
                    ->modalDescription('This will send the announcement to all target users via selected channels.')
                    ->modalSubmitActionLabel('Yes, Broadcast')
                    ->action(function (Announcement $record) {
                        try {
                            $record->broadcast();

                            \Filament\Notifications\Notification::make()
                                ->title('Announcement Broadcasted')
                                ->body("Global announcement '{$record->title}' has been successfully broadcasted to target users.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Broadcast Failed')
                                ->body('Failed to broadcast announcement: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // View Acknowledgment Status
                Tables\Actions\Action::make('acknowledgments')
                    ->label('View Acknowledgments')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn (Announcement $record): bool => 
                        $record->is_global && Auth::user()->hasRole(['admin', 'superadmin'])
                    )
                    ->modalContent(function (Announcement $record) {
                        $totalUsers = \App\Models\User::count();
                        $acknowledgedCount = count($record->acknowledged_by ?? []);
                        $pendingCount = $record->getUnacknowledgedCount();
                        
                        return new \Illuminate\Support\HtmlString("
                            <div class='space-y-4'>
                                <div class='grid grid-cols-3 gap-4'>
                                    <div class='text-center'>
                                        <div class='text-2xl font-bold text-blue-600'>{$totalUsers}</div>
                                        <div class='text-sm text-gray-600'>Total Users</div>
                                    </div>
                                    <div class='text-center'>
                                        <div class='text-2xl font-bold text-green-600'>{$acknowledgedCount}</div>
                                        <div class='text-sm text-gray-600'>Acknowledged</div>
                                    </div>
                                    <div class='text-center'>
                                        <div class='text-2xl font-bold text-orange-600'>{$pendingCount}</div>
                                        <div class='text-sm text-gray-600'>Pending</div>
                                    </div>
                                </div>
                                <div class='w-full bg-gray-200 rounded-full h-2.5'>
                                    <div class='bg-green-600 h-2.5 rounded-full' style='width: " . ($totalUsers > 0 ? ($acknowledgedCount / $totalUsers * 100) : 0) . "%'></div>
                                </div>
                                <p class='text-sm text-gray-600 text-center'>" . round($totalUsers > 0 ? ($acknowledgedCount / $totalUsers * 100) : 0, 1) . "% Complete</p>
                            </div>
                        ");
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('expire')
                        ->label('Expire Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Active') {
                                    $record->expire();
                                }
                            }
                        }),

                    Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Archived']);
                            }
                        }),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No announcements found')
            ->emptyStateDescription('Create your first announcement to get started.')
            ->emptyStateIcon('heroicon-o-megaphone');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}

