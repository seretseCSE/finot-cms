<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Services\PhoneFormattingService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class ParentGuardianTab
{
    public static function make(): Tab
    {
        return Tab::make('Parent/Guardian')
            ->icon('heroicon-o-user-group')
            ->schema([
                Section::make('Parent/Guardian Information')
                    ->schema([
                        Forms\Components\Repeater::make('parent_guardian_info')
                            ->label('Parent/Guardian Assignments')
                            ->schema([
                                Forms\Components\Select::make('parent_id')
                                    ->label('Select Parent/Guardian')
                                    ->options(function () {
                                        try {
                                            return \App\Models\ParentModel::query()
                                                ->where('is_active', true)
                                                ->orderBy('full_name')
                                                ->pluck('full_name', 'id')
                                                ->toArray();
                                        } catch (\Exception $e) {
                                            return [];
                                        }
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $parent = \App\Models\ParentModel::find($state);
                                            if ($parent) {
                                                $set('parent_name', $parent->full_name);
                                                $set('parent_phone', $parent->phone);
                                                $set('relationship', $parent->relationship_type);
                                            }
                                        }
                                    }),

                                Forms\Components\TextInput::make('parent_name')
                                    ->label('Parent/Guardian Name')
                                    ->required(),

                                Forms\Components\Select::make('relationship')
                                    ->label('Relationship')
                                    ->options([
                                        'Father' => 'Father',
                                        'Mother' => 'Mother',
                                        'Guardian' => 'Guardian',
                                        'GrandFather' => 'GrandFather',
                                        'GrandMother' => 'GrandMother',
                                        'Uncle' => 'Uncle',
                                        'Brother' => 'Brother',
                                        'Aunt' => 'Aunt',
                                        'Sister' => 'Sister',
                                        'Other' => 'Other',
                                    ])
                                    ->required()
                                    ->searchable(),

                                Forms\Components\TextInput::make('parent_phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->prefix(PhoneFormattingService::prefix())
                                    ->regex('/^[0-9]{9}$/')
                                    ->placeholder('912345678')
                                    ->helperText(PhoneFormattingService::helperText())
                                    ->maxLength(9)
                                    ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                    ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(
                                fn (array $state): ?string => $state['parent_name'] ?? 'New Parent/Guardian'
                            )
                            ->addActionLabel('+ Add Parent/Guardian'),
                    ])
                    ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                Section::make('Additional Kids Information')
                    ->description('Details specific to Sunday School level and talents.')
                    ->schema([
                        Forms\Components\Select::make('spiritual_education_level')
                            ->label('Spiritual Education Level')
                            ->options([
                                'Beginner' => 'Beginner',
                                'Intermediate' => 'Intermediate',
                                'Advanced' => 'Advanced',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('special_talents')
                            ->label('Special Talents')
                            ->rows(3),
                    ])
                    ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                Section::make('Historical Parent/Guardian Records (Read-Only)')
                    ->description('This member was previously registered as a Kid. Parent/guardian records from that period are preserved below for reference.')
                    ->schema([
                        Forms\Components\Placeholder::make('historical_parents')
                            ->label('')
                            ->content(fn ($record) => $record?->parentGuardians()
                                ->get()
                                ->map(
                                    fn ($pg) => "• **{$pg->parent_name}** ({$pg->relationship}) — ".
                                    ($pg->phone ?? 'No phone')
                                )
                                ->join("\n") ?: 'No historical parent records found.')
                            ->visible(fn ($record) => $record && $record->parentGuardians()->exists()),
                    ])
                    ->collapsed()
                    ->visible(
                        fn ($record, callable $get) => in_array($get('member_type'), ['Youth', 'Adult']) &&
                        $record && $record->parentGuardians()->exists()
                    ),
            ])
            ->visible(
                fn ($record, callable $get) => $get('member_type') === 'Kids' ||
                (in_array($get('member_type'), ['Youth', 'Adult']) && $record && $record->parentGuardians()->exists())
            );
    }
}
