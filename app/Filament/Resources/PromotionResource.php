<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Filament\Support\HidesFromNavigation;
use App\Models\AcademicYear;
use App\Models\Member;
use App\Models\Promotion;
use App\Models\SchoolClass;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = Promotion::class;

    public static function getNavigationLabel(): string
    {
        return 'Promotions';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('student_id')
                ->label('Student')
                ->options(fn () => Member::query()->orderBy('first_name')->get()->mapWithKeys(
                    fn (Member $member) => [$member->id => $member->full_name ?? trim($member->first_name.' '.$member->last_name)]
                ))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('from_class_id')
                ->label('From class')
                ->options(fn () => SchoolClass::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Select::make('to_class_id')
                ->label('To class')
                ->options(fn () => SchoolClass::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('academic_year_id')
                ->label('Academic year')
                ->options(fn () => AcademicYear::query()->orderBy('name', 'desc')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\DatePicker::make('promotion_date')
                ->required(),
            Forms\Components\Textarea::make('reason')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.first_name')
                    ->label('Student')
                    ->formatStateUsing(fn ($record) => $record->student?->full_name
                        ?? trim(($record->student?->first_name ?? '').' '.($record->student?->father_name ?? '')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                            $studentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('fromClass.name')->label('From'),
                Tables\Columns\TextColumn::make('toClass.name')->label('To'),
                Tables\Columns\TextColumn::make('academicYear.name')->label('Year'),
                Tables\Columns\TextColumn::make('promotion_date')->date()->sortable(),
            ])
            ->defaultSort('promotion_date', 'desc')
            ->recordActions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
