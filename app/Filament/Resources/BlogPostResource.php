<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-document-text'; }

    public static function getNavigationLabel(): string { return 'Blog Posts'; }

    public static function getNavigationGroup(): ?string { return 'Worship & Media'; }

    public static function getNavigationSort(): ?int { return 4; }

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
                Forms\Components\Section::make('Content')
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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publication Settings')
                    ->schema([
                        Forms\Components\DatePicker::make('publish_date')
                            ->label('Publish Date')
                            ->helperText('Leave empty for immediate publish')
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Scheduled' => 'Scheduled',
                                'Published' => 'Published',
                                'Archived' => 'Archived',
                            ])
                            ->required()
                            ->default('Draft')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'Published') {
                                    $set('publish_date', now()->toDateString());
                                }
                            }),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false)
                            ->helperText('Featured posts are highlighted on the blog page'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media & Tags')
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Featured Image')
                            ->disk('public')
                            ->directory('blog-images')
                            ->image()
                            ->imageEditor()
                            ->helperText('Optional: Featured image for the blog post'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags...')
                            ->separator(','),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image_url')
                    ->label('Image')
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/placeholder.jpg')),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('ethiopian_publish_date')
                    ->label('Publish Date')
                    ->sortable()
                    ->date(),

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
                        'Published' => 'Published',
                        'Archived' => 'Archived',
                    ]),

                Tables\Filters\Filter::make('publish_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('publish_date', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
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
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Scheduled') {
                                    $record->publish();
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Archived']);
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No blog posts found')
            ->emptyStateDescription('Create your first blog post to get started.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}

