<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseCategoryResource\Pages;
use Filament\Schemas\Schema;
use App\Models\CourseCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CourseCategoryResource extends BaseResource
{
    protected static ?string $model = CourseCategory::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationLabel(): string
    {
        return 'Course Categories';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Course Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canViewAny(): bool
    {
        return \App\Support\RoleGate::isAny(['superadmin', 'education_head', 'education_monitor']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\RoleGate::isAny(['education_head', 'education_monitor']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Category Information')
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Category')
                        ->placeholder('None (top-level folder)')
                        ->options(fn () => static::getTreeOptions())
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TextInput::make('name')
                        ->label('Name (English)')
                        ->required()
                        ->maxLength(255)
                        ->reactive()
                        ->afterStateUpdated(function ($set, $state) {
                            if (empty($state)) return;
                            $base = \Illuminate\Support\Str::slug($state);
                            $slug = $base;
                            $i = 2;
                            while (CourseCategory::where('slug', $slug)->exists()) {
                                $slug = "{$base}-{$i}";
                                $i++;
                            }
                            $set('slug', $slug);
                        }),
                    Forms\Components\TextInput::make('name_am')
                        ->label('Name (አማርኛ)')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(500),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon (emoji or SVG)')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label('URL Slug')
                        ->helperText('Auto-generated from English name')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('display_order')
                        ->label('Display Order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Active' => 'Active',
                            'Inactive' => 'Inactive',
                        ])
                        ->required()
                        ->default('Active'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name (EN)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name_am')
                    ->label('Name (አማ)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('courses_count')
                    ->label('Courses')
                    ->counts('courses')
                    ->alignCenter(),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ])
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_order')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseCategories::route('/'),
            'create' => Pages\CreateCourseCategory::route('/create'),
            'edit' => Pages\EditCourseCategory::route('/{record}/edit'),
        ];
    }

    public static function getTreeOptions(?int $excludeId = null): array
    {
        $options = [];
        $categories = CourseCategory::orderBy('display_order')->orderBy('name')->get();

        foreach ($categories as $cat) {
            if ($excludeId && $cat->id === $excludeId) continue;
            $prefix = str_repeat('─ ', $cat->depth);
            $options[$cat->id] = $prefix . ($cat->name_am ?? $cat->name);
        }

        return $options;
    }
}
