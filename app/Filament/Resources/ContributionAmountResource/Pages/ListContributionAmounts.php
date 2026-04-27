<?php

namespace App\Filament\Resources\ContributionAmountResource\Pages;

use App\Filament\Resources\ContributionAmountResource;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\ContributionAmount;
use App\Models\MemberGroup;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class ListContributionAmounts extends ListRecords
{
    protected static string $resource = ContributionAmountResource::class;

    // Matrix view properties
    public ?int $selectedAcademicYear = null;

    public array $contributionAmounts = [];

    public Collection $academicYears;

    public Collection $memberGroups;

    public Collection $existingAmounts;

    public string $viewMode = 'list'; // 'matrix' or 'list'

    public function mount(): void
    {
        $this->loadInitialData();
        $this->selectedAcademicYear = $this->getActiveAcademicYear()?->id;
        $this->loadContributionAmounts();
    }

    protected function loadInitialData(): void
    {
        $this->academicYears = AcademicYear::orderBy('name')->get();
        $this->memberGroups = MemberGroup::orderBy('name')->get();
        $this->loadExistingAmounts();
    }

    protected function getActiveAcademicYear(): ?AcademicYear
    {
        return AcademicYear::where('status', 'Active')->first();
    }

    protected function loadExistingAmounts(): void
    {
        $this->existingAmounts = ContributionAmount::with(['group', 'academicYear'])
            ->when($this->selectedAcademicYear, fn ($query) => $query->where('academic_year_id', $this->selectedAcademicYear))
            ->get();
    }

    public function loadContributionAmounts(): void
    {
        $this->loadExistingAmounts();

        if (! $this->selectedAcademicYear) {
            return;
        }

        $months = $this->getMonthNames();

        foreach ($this->memberGroups as $group) {
            foreach ($months as $month) {
                $existing = $this->existingAmounts
                    ->where('group_id', $group->id)
                    ->where('academic_year_id', $this->selectedAcademicYear)
                    ->where('month_name', $month)
                    ->first();

                $amount = $existing?->amount ?? 0;
                $amount = is_numeric($amount) ? (float) $amount : 0;
                $this->contributionAmounts[$group->id][$month] = $amount;
            }
        }
    }

    protected function getMonthNames(): array
    {
        return EthiopianDateHelper::getContributionMonths();
    }

    #[Computed]
    public function months(): array
    {
        return $this->getMonthNames();
    }

    public function updated($property): void
    {
        if ($property === 'selectedAcademicYear') {
            $this->loadContributionAmounts();
        }

        // Autosave contribution amounts when they change in matrix mode
        if ($this->viewMode === 'matrix' && str_starts_with($property, 'contributionAmounts.')) {
            $this->autosaveContributionAmount($property);
        }
    }

    protected function autosaveContributionAmount(string $property): void
    {
        $parts = explode('.', $property);
        if (count($parts) < 3) {
            return;
        }

        $groupId = (int) $parts[1];
        $month = $parts[2];
        $amount = $this->contributionAmounts[$groupId][$month] ?? 0;
        $amount = is_numeric($amount) ? (float) $amount : 0;

        try {
            DB::beginTransaction();

            if ($amount > 0) {
                ContributionAmount::updateOrCreate(
                    [
                        'group_id' => $groupId,
                        'academic_year_id' => $this->selectedAcademicYear,
                        'month_name' => $month,
                    ],
                    [
                        'amount' => $amount,
                        'effective_from' => now(),
                        'created_by' => Auth::id(),
                        'updated_at' => now(),
                    ]
                );
            } else {
                ContributionAmount::where('group_id', $groupId)
                    ->where('academic_year_id', $this->selectedAcademicYear)
                    ->where('month_name', $month)
                    ->delete();
            }

            DB::commit();
            $this->loadExistingAmounts();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Autosave failed for contribution amount', [
                'property' => $property,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fillAllGroups(): void
    {
        $defaultAmount = 100.00;

        foreach ($this->memberGroups as $group) {
            foreach ($this->getMonthNames() as $month) {
                $this->contributionAmounts[$group->id][$month] = $defaultAmount;
            }
        }
    }

    public function clearAllAmounts(): void
    {
        foreach ($this->memberGroups as $group) {
            foreach ($this->getMonthNames() as $month) {
                $this->contributionAmounts[$group->id][$month] = 0;
            }
        }
    }

    public function saveMatrix(): void
    {
        if (! $this->selectedAcademicYear) {
            Notification::make()
                ->title('Academic Year Required')
                ->body('Please select an academic year first.')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'selectedAcademicYear' => 'required|exists:academic_years,id',
            'contributionAmounts.*.*' => 'required|numeric|min:0|max:999999.99',
        ]);

        DB::beginTransaction();

        try {
            $createdCount = 0;
            $updatedCount = 0;
            $deletedCount = 0;

            $months = $this->getMonthNames();

            foreach ($this->memberGroups as $group) {
                foreach ($months as $month) {
                    $amount = $this->contributionAmounts[$group->id][$month] ?? 0;
                    $amount = is_numeric($amount) ? (float) $amount : 0;

                    if ($amount > 0) {
                        $record = ContributionAmount::updateOrCreate(
                            [
                                'group_id' => $group->id,
                                'academic_year_id' => $this->selectedAcademicYear,
                                'month_name' => $month,
                            ],
                            [
                                'amount' => $amount,
                                'effective_from' => now(),
                                'created_by' => Auth::id(),
                                'updated_at' => now(),
                            ]
                        );

                        if ($record->wasRecentlyCreated) {
                            $createdCount++;
                        } else {
                            $updatedCount++;
                        }
                    } else {
                        $deleted = ContributionAmount::where('group_id', $group->id)
                            ->where('academic_year_id', $this->selectedAcademicYear)
                            ->where('month_name', $month)
                            ->delete();

                        $deletedCount += $deleted;
                    }
                }
            }

            DB::commit();
            $this->loadExistingAmounts();

            Notification::make()
                ->title('Matrix Saved')
                ->body("Created: {$createdCount}, Updated: {$updatedCount}, Removed: {$deletedCount}")
                ->success()
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Save Failed')
                ->body('An error occurred while saving: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_view')
                ->label($this->viewMode === 'list' ? 'Matrix View' : 'List View')
                ->icon($this->viewMode === 'list' ? 'heroicon-o-table-cells' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->action(fn () => $this->viewMode = $this->viewMode === 'list' ? 'matrix' : 'list'),

            Actions\CreateAction::make()
                ->visible(fn () => ContributionAmountResource::canCreate() && $this->viewMode === 'list'),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    public function getViewData(): array
    {
        return [
            'viewMode' => $this->viewMode,
            'selectedAcademicYear' => $this->selectedAcademicYear,
            'academicYears' => $this->academicYears,
            'memberGroups' => $this->memberGroups,
            'contributionAmounts' => $this->contributionAmounts,
            'months' => $this->months,
        ];
    }
}
