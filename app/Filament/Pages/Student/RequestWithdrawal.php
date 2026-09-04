<?php

namespace App\Filament\Pages\Student;

use App\Models\StudentEnrollment;
use App\Models\WithdrawalRequest;
use App\Services\Movement\WithdrawalService;
use App\Support\RoleGate;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class RequestWithdrawal extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Request Withdrawal';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.student.request-withdrawal';

    public ?array $data = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-right-start-on-rectangle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'My Learning';
    }

    public static function getNavigationLabel(): string
    {
        return 'Request Withdrawal';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::is('student') && RoleGate::can('withdrawal.apply');
    }

    public function mount(): void
    {
        $this->form->fill([
            'reason' => '',
            'destination' => '',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000)
                    ->rows(4),
                TextInput::make('destination')
                    ->label('Destination (optional)')
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    public function enrollment(): ?StudentEnrollment
    {
        $memberId = RoleGate::user()?->member_id;

        if (! $memberId) {
            return null;
        }

        return StudentEnrollment::query()
            ->active()
            ->where('member_id', $memberId)
            ->latest()
            ->first();
    }

    public function existing(): ?WithdrawalRequest
    {
        $memberId = RoleGate::user()?->member_id;

        if (! $memberId) {
            return null;
        }

        return WithdrawalRequest::query()
            ->where('member_id', $memberId)
            ->latest()
            ->first();
    }

    public function submit(WithdrawalService $service): void
    {
        $enrollment = $this->enrollment();

        if (! $enrollment) {
            Notification::make()
                ->title('No active enrollment')
                ->body('You do not have an active enrollment.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        try {
            $service->apply(
                RoleGate::user(),
                $enrollment,
                $data['reason'],
                $data['destination'] ?: null
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Unable to submit withdrawal.';
            Notification::make()->title($message)->danger()->send();

            return;
        }

        Notification::make()
            ->title('Withdrawal request submitted')
            ->success()
            ->send();

        $this->form->fill([
            'reason' => '',
            'destination' => '',
        ]);
    }
}
