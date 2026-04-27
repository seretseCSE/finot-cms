<?php

namespace App\Filament\Resources;

use App\Filament\Exports\FinancialTransactionExporter;
use Filament\Schemas\Schema;
use App\Filament\Resources\FinancialTransactionResource\Pages;
use App\Models\BankAccount;
use App\Models\FinancialTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FinancialTransactionResource extends Resource
{
    protected static ?string $model = FinancialTransaction::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationLabel(): string
    {
        return 'Financial Transactions';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Transaction Details')
                    ->schema([
                        Select::make('type')
                            ->label('Transaction Type')
                            ->options([
                                'income' => 'Income',
                                'expense' => 'Expense',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('category', null)),

                        TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->default(FinancialTransaction::generateTransactionId())
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->required()
                                    ->numeric()
                                    ->step('0.01')
                                    ->prefix('ETB'),

                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'ETB' => 'Ethiopian Birr',
                                        'USD' => 'US Dollar',
                                        'EUR' => 'Euro',
                                    ])
                                    ->default('ETB')
                                    ->required(),

                                Forms\Components\DatePicker::make('transaction_date')
                                    ->label('Transaction Date')
                                    ->required()
                                    ->default(now()),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category')
                                    ->label(function (callable $get) {
                                        $type = $get('type');

                                        return $type === 'income' ? 'Income Category' : 'Expense Category';
                                    })
                                    ->options(function (callable $get) {
                                        $type = $get('type');

                                        return $type === 'income' ? [
                                            'tithes' => 'Tithes',
                                            'offering' => 'Offering',
                                            'donation' => 'Donation',
                                            'other' => 'Other',
                                        ] : [
                                            'salaries' => 'Salaries',
                                            'utilities' => 'Utilities',
                                            'maintenance' => 'Maintenance',
                                            'supplies' => 'Supplies',
                                            'other' => 'Other',
                                        ];
                                    })
                                    ->required(),

                                Forms\Components\TextInput::make('source')
                                    ->label('Source/Payer')
                                    ->placeholder('e.g., Church Member, Company Name, etc.')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'mobile_banking' => 'Mobile Banking',
                                'check' => 'Check',
                                'card' => 'Credit/Debit Card',
                                'other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Bank Account')
                            ->options(BankAccount::active()->pluck('account_name', 'id'))
                            ->placeholder('Select bank account if applicable')
                            ->nullable(),
                    ]),

                Section::make('Attachments')
                    ->schema([
                        Forms\Components\FileUpload::make('attachment_path')
                            ->label('Attachment (Receipt/Invoice)')
                            ->helperText('Upload receipt, invoice, or other proof document')
                            ->acceptedFileTypes(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])
                            ->directory('financial-attachments')
                            ->nullable(),

                        Forms\Components\Select::make('attachment_type')
                            ->label('Attachment Type')
                            ->options([
                                'receipt' => 'Receipt',
                                'invoice' => 'Invoice',
                                'other' => 'Other',
                            ])
                            ->required(),
                    ])
                    ->collapsible(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'income' => 'success',
                        'expense' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->sortable()
                    ->placeholder('No bank account'),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ]),

                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('Bank Account')
                    ->options(BankAccount::active()->pluck('account_name', 'id')),

                Tables\Filters\Filter::make('transaction_date')
                    ->label('Transaction Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(FinancialTransactionExporter::class)
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success'),
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
            'index' => Pages\ListFinancialTransactions::route('/'),
            'create' => Pages\CreateFinancialTransaction::route('/create'),
            'edit' => Pages\EditFinancialTransaction::route('/{record}/edit'),
        ];
    }

    public static function beforeSave(array $data): array
    {
        // Auto-approve transactions for users with appropriate permissions
        $user = auth()->user();
        if ($user && ($user->hasRole(['admin', 'superadmin', 'finance_head', 'nibret_hisab_head']))) {
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        }

        // Set recorded by
        $data['recorded_by'] = $user?->id;

        return $data;
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
