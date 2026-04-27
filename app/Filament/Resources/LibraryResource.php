<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LibraryResource\Pages;
use Filament\Schemas\Schema;
use App\Models\LibraryCategory;
use App\Models\LibraryResource as LibraryResourceModel;
use App\Models\LibrarySubcategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LibraryResource extends Resource
{
    protected static ?string $model = LibraryResourceModel::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Library Resources';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Resource Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => LibraryCategory::query()->where('status', 'Active')->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null)),

                        Forms\Components\Select::make('subcategory_id')
                            ->label('Subcategory')
                            ->options(function (Get $get): array {
                                $categoryId = $get('category_id');
                                if (! $categoryId) {
                                    return [];
                                }

                                return LibrarySubcategory::query()
                                    ->where('category_id', $categoryId)
                                    ->where('status', 'Active')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500),
                    ]),

                Section::make('File Upload')
                    ->schema([
                        Forms\Components\Select::make('file_type')
                            ->label('File Type')
                            ->options([
                                'pdf' => 'PDF Document',
                                'epub' => 'E-Book (EPUB)',
                                'audio' => 'Audio Book (MP3/M4A)',
                                'video' => 'Video (MP4)',
                                'doc' => 'Word Document',
                            ])
                            ->default('pdf')
                            ->required()
                            ->live(),

                        FileUpload::make('file_path')
                            ->label('File')
                            ->required()
                            ->disk('library')
                            ->acceptedFileTypes([
                                'application/pdf',                                          // PDF
                                'application/epub+zip',                                     // EPUB
                                'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/ogg',  // Audio
                                'video/mp4', 'video/mpeg', 'video/quicktime',               // Video
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Word
                            ])
                            ->directory('')
                            ->preserveFilenames()
                            ->downloadable()
                            ->openable()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    // Validate file size manually (50MB = 51200 KB)
                                    $maxSize = 51200; // KB
                                    try {
                                        $filePath = null;
                                        if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                            $filePath = $state->getFilename();
                                        } elseif (is_string($state)) {
                                            $filePath = $state;
                                        } elseif (is_array($state) && isset($state[0])) {
                                            $filePath = $state[0];
                                        }

                                        if ($filePath && str_starts_with($filePath, 'livewire-tmp/')) {
                                            if (Storage::exists($filePath)) {
                                                $sizeKB = Storage::size($filePath) / 1024;
                                                if ($sizeKB > $maxSize) {
                                                    $set('file_path', null);
                                                    Filament\Notifications\Notification::make()
                                                        ->danger()
                                                        ->title('File too large')
                                                        ->body('Maximum file size is 50MB. Your file is '.round($sizeKB, 2).'KB.')
                                                        ->send();
                                                }
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // If we can't check the size, let it proceed and handle in create method
                                        Log::warning('Could not validate file size: '.$e->getMessage());
                                    }
                                }
                            }),
                    ]),

                Section::make('Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subcategory.name')
                    ->label('Subcategory')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Size')
                    ->sortable(),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => LibraryCategory::query()->orderBy('name')->pluck('name', 'id')->all()),

                Tables\Filters\SelectFilter::make('subcategory_id')
                    ->label('Subcategory')
                    ->options(fn () => LibrarySubcategory::query()->orderBy('name')->pluck('name', 'id')->all()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->placeholder('All')
                    ->trueLabel('Featured')
                    ->falseLabel('Not Featured'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (LibraryResourceModel $record): void {
                        if ($record->file_path && Storage::disk('library')->exists($record->file_path)) {
                            Storage::disk('library')->delete($record->file_path);
                        }
                    }),
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (LibraryResourceModel $record): string => $record->file_url)
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLibraryResources::route('/'),
            'create' => Pages\CreateLibraryResource::route('/create'),
            'edit' => Pages\EditLibraryResource::route('/{record}/edit'),
        ];
    }
}
