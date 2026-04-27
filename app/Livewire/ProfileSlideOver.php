<?php

namespace App\Livewire;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ProfileSlideOver extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public bool $isOpen = false;

    protected $listeners = ['open-profile-slideover' => 'open'];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user) {
            $this->form->fill([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]);
        }
    }

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, ignoreRecord: true),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->nullable()
                            ->prefix(config('finot.phone_prefix', '+251'))
                            ->regex('/^[0-9]{9}$/')
                            ->placeholder('912345678')
                            ->maxLength(9),
                    ])->columns(2),

                Section::make('Change Password')
                    ->description('Leave empty if you don\'t want to change your password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->requiredWith('password'),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->confirmed()
                            ->nullable()
                            ->minLength(8),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->dehydrated(false),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $data = $this->form->getState();

        if (!empty($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                $this->addError('data.current_password', 'The provided password does not match your current password.');
                return;
            }

            $user->update([
                'password' => Hash::make($data['password']),
            ]);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => isset($data['phone']) && $data['phone'] ? config('finot.phone_prefix', '+251').$data['phone'] : null,
        ]);

        Notification::make()
            ->title('Profile Updated')
            ->success()
            ->send();

        $this->isOpen = false;
    }

    public function render()
    {
        return view('livewire.profile-slide-over');
    }
}
