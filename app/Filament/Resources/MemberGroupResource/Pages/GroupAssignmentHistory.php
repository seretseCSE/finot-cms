<?php

namespace App\Filament\Resources\MemberGroupResource\Pages;

use App\Filament\Resources\MemberGroupResource;
use App\Helpers\EthiopianDateHelper;
use App\Models\MemberGroupAssignment;
use Carbon\Carbon;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupAssignmentHistory extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MemberGroupResource::class;

    protected string $view = 'filament.resources.member-group-resource.pages.group-assignment-history';

    protected function getTableQuery(): Builder
    {
        return MemberGroupAssignment::query()
            ->forGroup($this->getRecord()->getKey())
            ->with(['member', 'assigner', 'remover'])
            ->orderByDesc('effective_from');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member Name')
                    ->url(fn (MemberGroupAssignment $record): string => \App\Filament\Resources\MemberResource::getUrl('edit', ['record' => $record->member_id]))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('member.member_code')
                    ->label('Member Code')
                    ->searchable(),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Assigned Date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_to')
                    ->label('Removed Date')
                    ->formatStateUsing(function ($state): string {
                        if (blank($state)) {
                            return 'Active';
                        }

                        return app(EthiopianDateHelper::class)->toString($state);
                    }),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function (MemberGroupAssignment $record): string {
                        $from = $record->effective_from ? Carbon::parse($record->effective_from) : null;
                        $to = $record->effective_to ? Carbon::parse($record->effective_to) : now();

                        if (! $from) {
                            return '';
                        }

                        $days = $from->diffInDays($to);

                        return $days . ' days';
                    }),

                Tables\Columns\TextColumn::make('assigner.name')
                    ->label('Assigned By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('remover.name')
                    ->label('Removed By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query): Builder => $query->active()),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $to = $data['to'] ?? null;

                        if ($from) {
                            $query->whereDate('effective_from', '>=', $from);
                        }

                        if ($to) {
                            $query->whereDate('effective_from', '<=', $to);
                        }

                        return $query;
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}

