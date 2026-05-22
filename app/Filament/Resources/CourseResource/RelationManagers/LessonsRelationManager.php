<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Lessons';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('title')
                ->label('Title (English)')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('title_am')
                ->label('Title (አማርኛ)')
                ->maxLength(255),
            Forms\Components\TextInput::make('display_order')
                ->label('Order')
                ->numeric()
                ->default(0),
            Forms\Components\Select::make('status')
                ->options([
                    'Draft' => 'Draft',
                    'Published' => 'Published',
                ])
                ->default('Draft'),
            Forms\Components\RichEditor::make('content')
                ->label('Content (English)'),
            Forms\Components\RichEditor::make('content_am')
                ->label('Content (አማርኛ)'),
        ])
        ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('display_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                    ])
                    ->alignCenter(),
            ])
            ->defaultSort('display_order')
            ->reorderable('display_order')
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }
}
