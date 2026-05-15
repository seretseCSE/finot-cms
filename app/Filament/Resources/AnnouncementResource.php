<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Services\UploadSanitizer;
use Filament\Schemas\Schema;
use App\Models\Announcement;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AnnouncementResource extends BaseResource
{
    protected static ?string $model = Announcement::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public static function getNavigationLabel(): string
    {
        return 'Announcements';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Content')
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

                        Forms\Components\FileUpload::make('image')
                            ->label('Image')
                            ->image()
                            ->imageEditor()
                            ->directory('announcements')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->nullable()
                            ->helperText('Optional image for the announcement')
                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('announcements', 'public', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])),
                    ])
                    ->columns(2),

                Section::make('Schedule & Display')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Scheduled' => 'Scheduled',
                                'Active' => 'Active',
                            ])
                            ->default('Draft')
                            ->required()
                            ->live()
                            ->helperText('Scheduled announcements will be automatically published on the start date.'),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false)
                            ->helperText(fn (callable $get) => $get('status') === 'Scheduled' ? 'Announcement will be published on this date.' : 'When the announcement becomes active.'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->helperText('Leave empty for ongoing announcement')
                            ->native(false)
                            ->afterOrEqual('start_date'),

                        Forms\Components\Toggle::make('is_urgent')
                            ->label('Is Urgent')
                            ->default(false)
                            ->helperText('Urgent announcements will have red border and be pinned to top'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/placeholder/announcement.png'))
                    ->getStateUsing(function ($record) {
                        return $record->image ? Storage::url($record->image) : null;
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgent')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('ethiopian_start_date')
                    ->label('Start Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ethiopian_end_date')
                    ->label('End Date')
                    ->formatStateUsing(fn ($record) => $record->ethiopian_end_date ?: 'Ongoing')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not published'),

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
                        'Draft' => 'Draft',
                        'Scheduled' => 'Scheduled',
                        'Active' => 'Active',
                        'Expired' => 'Expired',
                        'Archived' => 'Archived',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
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
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Announcement')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn () => static::canCreate()),
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
