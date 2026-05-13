<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Enums\MemberStatus;
use App\Enums\Roles;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Auth;

class StatusHistoryTab
{
    public static function make(): Tab
    {
        return Tab::make('Status & History')
            ->icon('heroicon-o-clock')
            ->schema([
                Section::make('Member Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(MemberStatus::getAll())
                            ->enum(MemberStatus::class)
                            ->required()
                            ->disabled(fn () => ! Auth::user()->hasRole(Roles::HR_MANAGERS))
                            ->live(),
                    ]),

                Section::make('Assignment History')
                    ->schema([
                        Forms\Components\Placeholder::make('assignment_history')
                            ->label('Recent Group Assignments')
                            ->content(fn ($record) => $record?->groupAssignments()
                                ->with('group')
                                ->latest()
                                ->take(5)
                                ->get()
                                ->map(
                                    fn ($assignment) => $assignment->group->name.' - '.
                                    ($assignment->assigned_at?->format('M d, Y') ?? 'No date')
                                )
                                ->join("\n") ?: 'No assignments yet')
                            ->visible(fn ($record) => $record && $record->groupAssignments()->exists()),
                    ]),
            ]);
    }
}
