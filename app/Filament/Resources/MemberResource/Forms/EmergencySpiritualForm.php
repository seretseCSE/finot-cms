<?php

namespace App\Filament\Resources\MemberResource\Forms;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class EmergencySpiritualForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
            Section::make('Emergency Contact')
                ->description('Who to contact in case of an emergency.')
                ->schema([
                    TextInput::make('emergency_contact_name')
                        ->label('Emergency Contact Name')
                        ->required()
                        ->maxLength(200),

                    TextInput::make('emergency_contact_phone')
                        ->label('Emergency Contact Phone')
                        ->required()
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->regex('/^[0-9]{9}$/')
                        ->placeholder('912345678')
                        ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->formatStateUsing(function ($state) {
                            $prefix = config('finot.phone_prefix', '+251');

                            return $state ? preg_replace('/^('.preg_quote($prefix, '/').'|0)/', '', $state) : null;
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),
                ])
                ->columns(2),

            Section::make('Spiritual Information')
                ->description('Details regarding the member\'s confession father.')
                ->schema([
                    TextInput::make('confession_father_name')
                        ->label("Confession Father's Name")
                        ->maxLength(200),

                    TextInput::make('confession_father_phone')
                        ->label("Confession Father's Phone")
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->regex('/^[0-9]{9}$/')
                        ->placeholder('912345678')
                        ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->formatStateUsing(function ($state) {
                            $prefix = config('finot.phone_prefix', '+251');

                            return $state ? preg_replace('/^('.preg_quote($prefix, '/').'|0)/', '', $state) : null;
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),
                ])
                ->columns(2),

            Section::make('Member Status')
                ->schema([
                    \App\Filament\Forms\Components\CustomOptionSelect::make('status')
                        ->label('Status')
                        ->customOptions('member_status', [
                            'Draft' => 'Draft',
                            'Active' => 'Active',
                            'Former' => 'Former',
                        ])
                        ->required()
                        ->disabled(fn () => ! Auth::user()->hasRole(['hr_head', 'admin', 'superadmin'])),
                ]),

            Section::make('Assignment History')
                ->schema([
                    Placeholder::make('assignment_history')
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
        ];
    }
}
