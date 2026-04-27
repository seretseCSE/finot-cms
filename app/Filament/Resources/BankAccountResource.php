<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use Filament\Schemas\Schema;
use App\Models\BankAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bank Accounts';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Account Information')
                    ->schema([
                        TextInput::make('account_number')
                            ->label('Account Number')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        TextInput::make('account_name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('bank_name')
                            ->label('Bank Name')
                            ->options([
                                'commercial_bank_of_ethiopia' => 'Commercial Bank of Ethiopia',
                                'dashen_bank' => 'Dashen Bank',
                                'awash_bank' => 'Awash Bank',
                                'wegagen_bank' => 'Wegagen Bank',
                                'nib_international_bank' => 'NIB International Bank',
                                'cooperative_bank_of_oromia' => 'Cooperative Bank of Oromia',
                                'berhan_bank' => 'Berhan Bank',
                                'bunna_bank' => 'Bunna Bank',
                                'zemen_bank' => 'Zemen Bank',
                                'abyssinia_bank' => 'Abyssinia Bank',
                                'development_bank_of_ethiopia' => 'Development Bank of Ethiopia',
                                'other' => 'Other',
                            ])
                            ->required(),

                        TextInput::make('branch_name')
                            ->label('Branch Name')
                            ->maxLength(255),

                        Select::make('account_type')
                            ->label('Account Type')
                            ->options([
                                'current' => 'Current Account',
                                'savings' => 'Savings Account',
                                'fixed_deposit' => 'Fixed Deposit',
                                'checking' => 'Checking Account',
                            ])
                            ->default('current')
                            ->required(),
                    ]),

                Section::make('Balance & Currency')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('current_balance')
                                    ->label('Current Balance')
                                    ->numeric()
                                    ->step('0.01')
                                    ->prefix('ETB')
                                    ->default(0),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'ETB' => 'Ethiopian Birr',
                                        'USD' => 'US Dollar',
                                        'EUR' => 'Euro',
                                    ])
                                    ->default('ETB')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone_number')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->prefix(config('finot.phone_prefix', '+251'))
                                    ->regex('/^[0-9]{9}$/')
                                    ->placeholder('912345678')
                                    ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                                    ->maxLength(9)
                                    ->formatStateUsing(function ($state) {
                                        $prefix = config('finot.phone_prefix', '+251');

                                        return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
                                    })
                                    ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                            ]),

                        Textarea::make('address')
                            ->label('Address')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->collapsible(),

                Section::make('Status & Notes')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank Name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('branch_name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('ETB')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_name')
                    ->label('Bank Name')
                    ->options([
                        'commercial_bank_of_ethiopia' => 'Commercial Bank of Ethiopia',
                        'dashen_bank' => 'Dashen Bank',
                        'awash_bank' => 'Awash Bank',
                        'wegagen_bank' => 'Wegagen Bank',
                        'nib_international_bank' => 'NIB International Bank',
                        'cooperative_bank_of_oromia' => 'Cooperative Bank of Oromia',
                        'berhan_bank' => 'Berhan Bank',
                        'bunna_bank' => 'Bunna Bank',
                        'zemen_bank' => 'Zemen Bank',
                        'abyssinia_bank' => 'Abyssinia Bank',
                        'development_bank_of_ethiopia' => 'Development Bank of Ethiopia',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('account_type')
                    ->label('Account Type')
                    ->options([
                        'current' => 'Current Account',
                        'savings' => 'Savings Account',
                        'fixed_deposit' => 'Fixed Deposit',
                        'checking' => 'Checking Account',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All accounts')
                    ->trueLabel('Active accounts only')
                    ->falseLabel('Inactive accounts only'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'view' => Pages\ViewBankAccount::route('/{record}'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']);
    }
}
