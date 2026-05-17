<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\UploadSanitizer;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProductResource extends BaseResource
{
    protected static ?string $model = Product::class;

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($set, $state, $record) {
                            if (! $record?->slug) {
                                $set('slug', Product::generateUniqueSlug($state));
                            }
                        })
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Auto-generated from name. Used in the URL.'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100)
                        ->placeholder('e.g. Books, Apparel'),

                    Forms\Components\TextInput::make('price')
                        ->label('Price (ETB)')
                        ->required()
                        ->numeric()
                        ->prefix('ETB')
                        ->minValue(0)
                        ->step(0.01),

                    Forms\Components\TextInput::make('stock_quantity')
                        ->label('Stock Quantity')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0),

                    Forms\Components\Toggle::make('status')
                        ->label('Active')
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger')
                        ->formatStateUsing(fn ($state) => $state === 'active')
                        ->dehydrateStateUsing(fn ($state) => $state ? 'active' : 'inactive'),

                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => Auth::id()),
                ])
                ->columns(2),

            Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('Product Image')
                        ->disk('public')
                        ->directory('products')
                        ->image()
                        ->imageEditor()
                        ->maxSize(4096) // 4MB for product images
                        ->helperText('Optional: Product display image')
                        ->saveUploadedFileUsing(UploadSanitizer::saveCallback('products', 'public', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->size(60)
                    ->square()
                    ->defaultImageUrl(url('/placeholder.jpg')),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('ETB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status === 'active' ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn () => Product::distinct()->whereNotNull('category')->pluck('category', 'category')->toArray()),
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
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No products found')
            ->emptyStateDescription('Create your first product to get started.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
