<?php

namespace App\Filament\Widgets\Tables;

use App\Enums\MemberStatus;
use App\Models\Member;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentMembersTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Member::latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('member_code')
                    ->label('Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === MemberStatus::ACTIVE => 'success',
                        $state === MemberStatus::MEMBER => 'info',
                        $state === MemberStatus::FORMER => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('gender')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->label('Registered'),
            ])
            ->paginated(false);
    }
}
