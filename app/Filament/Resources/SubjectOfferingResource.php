<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectOfferingResource\Pages;
use App\Models\Assessment;
use App\Models\SubjectOffering;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubjectOfferingResource extends BaseResource
{
    protected static ?string $model = SubjectOffering::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-book-open';
    }

    public static function getNavigationLabel(): string
    {
        return 'Subject offerings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('batch_year_id')
                ->label('Batch year')
                ->relationship('batchYear', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('term_id')
                ->label('Semester')
                ->relationship('term', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('class_id')
                ->label('Class / section')
                ->relationship('class', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\Select::make('subject_id')
                ->relationship('subject', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('teacher_id')
                ->relationship('teacher', 'full_name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('max_score')
                ->numeric()
                ->default(100)
                ->required(),
            Forms\Components\Repeater::make('assessments')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('max_score')->numeric()->default(100)->required(),
                    Forms\Components\TextInput::make('weight')->numeric()->default(100)->required(),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_open')->default(true),
                ])
                ->orderColumn('sort_order')
                ->defaultItems(1)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batchYear.name')->label('Batch year')->sortable(),
                Tables\Columns\TextColumn::make('term.name')->label('Semester'),
                Tables\Columns\TextColumn::make('class.name')->label('Class'),
                Tables\Columns\TextColumn::make('subject.name')->searchable(),
                Tables\Columns\TextColumn::make('teacher.full_name')->label('Teacher'),
                Tables\Columns\TextColumn::make('assessments_count')->counts('assessments')->label('Assessments'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('add_assessment')
                    ->form([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('max_score')->numeric()->default(100)->required(),
                        Forms\Components\TextInput::make('weight')->numeric()->default(100)->required(),
                    ])
                    ->action(function (SubjectOffering $record, array $data) {
                        Assessment::query()->create([
                            'subject_offering_id' => $record->id,
                            'name' => $data['name'],
                            'max_score' => $data['max_score'],
                            'weight' => $data['weight'],
                            'sort_order' => ($record->assessments()->max('sort_order') ?? 0) + 1,
                            'is_open' => true,
                            'created_by' => Auth::id(),
                        ]);
                        Notification::make()->title('Assessment added')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjectOfferings::route('/'),
            'create' => Pages\CreateSubjectOffering::route('/create'),
            'edit' => Pages\EditSubjectOffering::route('/{record}/edit'),
        ];
    }
}
