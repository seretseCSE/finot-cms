<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Services\UploadSanitizer;
use Filament\Schemas\Schema;
use App\Models\MediaItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MediaResource extends BaseResource
{
    protected static ?string $model = MediaItem::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Media';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Media Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Radio::make('type')
                            ->label('Type')
                            ->options([
                                'Photo' => 'Photo',
                                'Video' => 'Video',
                            ])
                            ->required()
                            ->inline()
                            ->live(),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->options(function () {
                                return \App\Models\MediaCategory::where('status', 'Active')
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
                                if (! $categoryId) {
                                    return [];
                                }

                                return \App\Models\MediaSubcategory::where('category_id', $categoryId)
                                    ->where('status', 'Active')
                                    ->orderBy('display_order')
                                    ->pluck('name', 'id');
                            })
                            ->disabled(fn (callable $get) => ! $get('category_id')),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\TextInput::make('event_album')
                            ->label('Event Album')
                            ->maxLength(255)
                            ->helperText('Optional: Group related media items together'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags...')
                            ->separator(','),
                    ])
                    ->columns(2),

                Section::make('File Upload')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label(fn ($livewire) => $livewire instanceof Pages\CreateMedia ? 'Files' : 'File')
                            ->required()
                            ->multiple(fn ($livewire) => $livewire instanceof Pages\CreateMedia)
                            ->reorderable()
                            ->appendFiles()
                            ->directory('media')
                            ->disk('public')
                            ->visibility('public')
                            ->enableOpen()
                            ->enableDownload()
                            ->maxFiles(10) // Limit to 10 files at once
                            ->acceptedFileTypes(function (callable $get) {
                                $type = $get('type');
                                if ($type === 'Photo') {
                                    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                } elseif ($type === 'Video') {
                                    return ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
                                }
                                return ['image/jpeg', 'image/png']; // fallback
                            })
                            ->maxSize(function (callable $get) {
                                return $get('type') === 'Photo' ? 20480 : 51200; // 20MB for photos, 37.5MB for videos
                            })
                            ->helperText(function (callable $get) {
                                $type = $get('type');
                                $typeLabel = $type === 'Photo' ? 'Photos' : 'Videos';
                                $formats = $type === 'Photo' ? 'JPG, PNG, GIF, WEBP' : 'MP4, MOV, AVI, WEBM';
                                $size = $type === 'Photo' ? '20MB' : '37.5MB';
                                return "Current type: {$type}. {$formats} files, max {$size} each. You can upload up to 10 files at once.";
                            })
                            ->imageEditor(fn (callable $get) => $get('type') === 'Photo')
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('media', 'public', [
                                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
                            ])),
                    ]),

                Section::make('Visibility Settings')
                    ->schema([
                        Forms\Components\Select::make('visibility')
                            ->label('Visibility')
                            ->options([
                                'Public' => 'Public',
                                'Members Only' => 'Members Only',
                                'Department Only' => 'Department Only',
                            ])
                            ->required()
                            ->default('Public')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state === 'Department Only') {
                                    $set('department_id', Auth::user()->department_id);
                                }
                            }),

                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->default(fn () => Auth::user()->department_id)
                            ->visible(fn (callable $get) => $get('visibility') === 'Department Only')
                            ->required(fn (callable $get) => $get('visibility') === 'Department Only'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Thumbnail')
                    ->getStateUsing(function ($record) {
                        if ($record->type === 'Photo') {
                            return $record->file_url;
                        }

                        // For videos, you could implement thumbnail generation
                        return null;
                    })
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/placeholder.jpg')),

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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('type')
                    ->label('Type')
                    ->icon(fn ($record) => $record->type_icon),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Size')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_album')
                    ->label('Album')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn ($record) => $record->visibility_color),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'Photo' => 'Photo',
                        'Video' => 'Video',
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'Public' => 'Public',
                        'Members Only' => 'Members Only',
                        'Department Only' => 'Department Only',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('created_at', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\Action::make('view_new_tab')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record): string => static::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('change_visibility')
                        ->label('Change Visibility')
                        ->icon('heroicon-o-eye')
                        ->form([
                            Forms\Components\Select::make('visibility')
                                ->label('Visibility')
                                ->options([
                                    'Public' => 'Public',
                                    'Members Only' => 'Members Only',
                                    'Department Only' => 'Department Only',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                if (static::canEdit($record)) {
                                    $record->update(['visibility' => $data['visibility']]);
                                }
                            }
                        }),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Media')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No media items found')
            ->emptyStateDescription('Upload your first media item to get started.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->applyVisibilityRestrictions();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
            'view' => Pages\ViewMedia::route('/{record}'),
        ];
    }
}
