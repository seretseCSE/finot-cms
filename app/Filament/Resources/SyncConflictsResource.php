<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SyncConflictsResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AttendanceSyncConflict;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SyncConflictsResource extends Resource
{
    protected static ?string $model = AttendanceSyncConflict::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-exclamation-triangle'; }

    public static function getNavigationLabel(): string { return 'Sync Conflicts'; }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AttendanceSyncConflict::query()->with(['student', 'session', 'firstUser', 'secondUser']))
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Student')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('session.class.name')
                    ->label('Class')
                    ->sortable(),
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_value')
                    ->label('First Value')
                    ->sortable(),
                Tables\Columns\TextColumn::make('second_value')
                    ->label('Second Value (Winner)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('firstUser.name')
                    ->label('First User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('secondUser.name')
                    ->label('Second User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Conflict Time')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->relationship('session.class')
                    ->getOptionLabelUsing(fn ($record) => $record->name),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->required(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->required(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if ($from && $until) {
                            $query->whereHas('session', fn ($q) => $q->whereBetween('session_date', [$from, $until]));
                        }
                        return $query;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncConflicts::route('/'),
        ];
    }
}

