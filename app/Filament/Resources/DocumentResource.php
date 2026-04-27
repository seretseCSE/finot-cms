<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Services\UploadSanitizer;
use Filament\Schemas\Schema;
use App\Models\Department;
use App\Models\Document;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document';
    }

    public static function getNavigationLabel(): string
    {
        return 'Documents';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Archives';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    /**
     * Determine if the current user is a department head.
     */
    protected static function isDepartmentHead(?Model $record = null): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check if user is head of any department (or specific record's department)
        $query = Department::query()->where('head_user_id', $user->id);

        if ($record?->department_id) {
            $query->where('id', $record->department_id);
        }

        return $query->exists();
    }

    /**
     * Determine if the current user is a department secretary.
     */
    protected static function isDepartmentSecretary(?Model $record = null): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $query = Department::query()->where('secretary_user_id', $user->id);

        if ($record?->department_id) {
            $query->where('id', $record->department_id);
        }

        return $query->exists();
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'superadmin'])
            || $user->hasPermissionTo('documents.upload')
            || static::isDepartmentHead()
            || static::isDepartmentSecretary();
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Superadmin and admin can edit everything
        if ($user->hasRole(['admin', 'superadmin'])) {
            return true;
        }

        // Original uploader can edit their own documents
        if ($record->uploaded_by === $user->id) {
            return true;
        }

        // Department head can edit documents in their department
        if (static::isDepartmentHead($record)) {
            return true;
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Superadmin and admin can delete everything
        if ($user->hasRole(['admin', 'superadmin'])) {
            return true;
        }

        // Department head can delete documents in their department
        if (static::isDepartmentHead($record)) {
            return true;
        }

        return false;
    }

    /**
     * Scope documents by visibility and department access.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if (! $user) {
            return $query->where('visibility', 'Public');
        }

        // Superadmin and admin can see all documents
        if ($user->hasRole(['superadmin', 'admin'])) {
            return $query;
        }

        // Other users: Public + Members Only (any dept) + Department Only (own dept)
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereIn('visibility', ['Public', 'Members Only'])
                ->orWhere(function (Builder $sq) use ($user): void {
                    $sq->where('visibility', 'Department Only')
                        ->where('department_id', $user->department_id);
                });
        });
    }

    public static function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isAdmin = $user?->hasRole(['admin', 'superadmin']);

        return $schema->components([
                Section::make('Document Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags')
                            ->separator(',')
                            ->splitKeys(['Tab', ',']),

                        Forms\Components\DatePicker::make('document_date')
                            ->label('Document Date')
                            ->native(false),

                        Forms\Components\Select::make('visibility')
                            ->label('Visibility')
                            ->options([
                                'Public' => 'Public',
                                'Members Only' => 'Members Only',
                                'Department Only' => 'Department Only',
                            ])
                            ->default('Department Only')
                            ->required(),

                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(fn () => Department::query()->where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default($user?->department_id)
                            ->disabled(! $isAdmin)
                            ->hint($isAdmin ? null : 'Auto-filled from your department'),
                    ]),

                Section::make('File Upload')
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('File')
                            ->required()
                            ->disk('documents')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->directory('')
                            ->downloadable()
                            ->openable()
                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('', 'documents', [
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'image/jpeg',
                                'image/png',
                            ])),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\IconColumn::make('file_type_icon')
                    ->label('Type')
                    ->icon(fn (string $state): string => $state)
                    ->tooltip(fn (Document $record): string => strtoupper($record->file_type)),

                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Public' => 'success',
                        'Members Only' => 'info',
                        'Department Only' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name_en')
                    ->label('Department')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tags')
                    ->label('Tags')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ethiopian_document_date')
                    ->label('Document Date')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('document_date', $direction)),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Size')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('file_size_kb', $direction)),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'Public' => 'Public',
                        'Members Only' => 'Members Only',
                        'Department Only' => 'Department Only',
                    ]),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::query()->orderBy('name_en')->pluck('name_en', 'id')->all()),

                Tables\Filters\Filter::make('document_date')
                    ->label('Document Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label('To')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('document_date', '>=', $date))
                            ->when($data['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('document_date', '<=', $date));
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->before(function (Document $record): void {
                        if ($record->file_path && Storage::disk('documents')->exists($record->file_path)) {
                            Storage::disk('documents')->delete($record->file_path);
                        }
                    }),
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record): string => $record->file_url)
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->before(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->file_path && Storage::disk('documents')->exists($record->file_path)) {
                                    Storage::disk('documents')->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Document')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No documents found')
            ->emptyStateDescription('Upload your first document to get started.')
            ->emptyStateIcon('heroicon-o-document')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
