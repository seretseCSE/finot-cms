<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SongResource\Pages;
use App\Models\Song;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SongResource extends Resource
{
    protected static ?string $model = Song::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-musical-note'; }

    public static function getNavigationLabel(): string { return 'Songs'; }

    public static function getNavigationGroup(): ?string { return 'Worship & Media'; }

    public static function getNavigationSort(): ?int { return 2; }

    public static function canViewAny(): bool
    {
        return Auth::check(); // All authenticated users can view songs
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Song Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->options(function () {
                                return \App\Models\SongCategory::where('status', 'Active')
                                    ->orderBy('display_order')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('subcategory_id', null)),

                        Forms\Components\Select::make('subcategory_id')
                            ->label('Sub-category')
                            ->relationship('subcategory', 'name')
                            ->options(function (callable $get) {
                                $categoryId = $get('category_id');
                                if (!$categoryId) {
                                    return [];
                                }
                                return \App\Models\SongSubcategory::where('category_id', $categoryId)
                                    ->where('status', 'Active')
                                    ->orderBy('display_order')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->disabled(fn (callable $get) => !$get('category_id')),

                        Forms\Components\RichEditor::make('lyrics')
                            ->label('Lyrics')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bullet-list',
                                'ordered-list',
                            ])
                            ->helperText('Basic formatting only: bold, italic, lists'),

                        Forms\Components\TextInput::make('artist')
                            ->label('Artist')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media Files')
                    ->schema([
                        Forms\Components\FileUpload::make('audio_file')
                            ->label('Audio File')
                            ->disk('songs-audio')
                            ->directory('songs-audio')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/wav'])
                            ->maxSize(20480) // 20MB
                            ->helperText('MP3 or WAV files, max 20MB')
                            ->visibility('public'),

                        Forms\Components\FileUpload::make('video_file')
                            ->label('Video File')
                            ->disk('songs-video')
                            ->directory('songs-video')
                            ->acceptedFileTypes(['video/mp4'])
                            ->maxSize(51200) // 50MB
                            ->helperText('MP4 files, max 50MB')
                            ->visibility('public'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive songs will not be displayed on public website'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('song_code')
                    ->label('Song Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subcategory.name')
                    ->label('Sub-category')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('has_audio')
                    ->label('Audio')
                    ->boolean()
                    ->trueIcon('heroicon-o-musical-note')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('has_video')
                    ->label('Video')
                    ->boolean()
                    ->trueIcon('heroicon-o-video-camera')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('artist')
                    ->label('Artist')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->visible(fn () => Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin'])),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('subcategory_id')
                    ->label('Sub-category')
                    ->relationship('subcategory', 'name'),

                Tables\Filters\TernaryFilter::make('has_audio')
                    ->label('Has Audio')
                    ->placeholder('All')
                    ->trueLabel('With Audio')
                    ->falseLabel('Without Audio')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('audio_file'),
                        false: fn ($query) => $query->whereNull('audio_file'),
                    ),

                Tables\Filters\TernaryFilter::make('has_video')
                    ->label('Has Video')
                    ->placeholder('All')
                    ->trueLabel('With Video')
                    ->falseLabel('Without Video')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('video_file'),
                        false: fn ($query) => $query->whereNull('video_file'),
                    ),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                        }),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn () => Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin']))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin'])),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No songs found')
            ->emptyStateDescription('Add your first song to get started.')
            ->emptyStateIcon('heroicon-o-musical-note');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSongs::route('/'),
            'create' => Pages\CreateSong::route('/create'),
            'edit' => Pages\EditSong::route('/{record}/edit'),
        ];
    }
}

