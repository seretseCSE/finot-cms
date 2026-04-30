<?php

namespace App\Filament\Pages;

use App\Models\AidDistribution;
use App\Models\Beneficiary;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CharityReport extends Page
{
    protected static ?string $title = 'Charity Report';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-pie';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function getView(): string
    {
        return 'filament.pages.charity-report';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public ?string $date_from = null;

    public ?string $date_to = null;

    public ?string $aid_type = null;

    public function mount(): void
    {
        $this->date_from = now()->subMonth()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
    }

    public function getBeneficiaryData(): array
    {
        return [
            'total' => Beneficiary::count(),
            'active' => Beneficiary::where('status', 'Active')->count(),
            'inactive' => Beneficiary::where('status', 'Inactive')->count(),
            'completed' => Beneficiary::where('status', 'Completed')->count(),
            'by_type' => Beneficiary::select('type', DB::raw('count(*) as count'))->groupBy('type')->pluck('count', 'type')->toArray(),
            'by_need_category' => Beneficiary::select('need_category', DB::raw('count(*) as count'))->groupBy('need_category')->pluck('count', 'need_category')->toArray(),
        ];
    }

    public function getDistributionData(): array
    {
        $query = AidDistribution::query()
            ->when($this->date_from, fn ($q) => $q->whereDate('distribution_date', '>=', $this->date_from))
            ->when($this->date_to, fn ($q) => $q->whereDate('distribution_date', '<=', $this->date_to))
            ->when($this->aid_type, fn ($q) => $q->where('aid_type', $this->aid_type));

        return [
            'total_distributions' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'average_amount' => $query->avg('amount'),
            'locked_count' => (clone $query)->where('is_locked', true)->count(),
            'by_type' => AidDistribution::query()
                ->when($this->date_from, fn ($q) => $q->whereDate('distribution_date', '>=', $this->date_from))
                ->when($this->date_to, fn ($q) => $q->whereDate('distribution_date', '<=', $this->date_to))
                ->select('aid_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('aid_type')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->aid_type => ['count' => $item->count, 'total' => $item->total]])
                ->toArray(),
            'monthly_trend' => AidDistribution::query()
                ->when($this->date_from, fn ($q) => $q->whereDate('distribution_date', '>=', $this->date_from))
                ->when($this->date_to, fn ($q) => $q->whereDate('distribution_date', '<=', $this->date_to))
                ->select(DB::raw(match (DB::getDriverName()) {
                    'sqlite' => "strftime('%Y-%m', distribution_date) as month",
                    default => "DATE_FORMAT(distribution_date, '%Y-%m') as month",
                }), DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->month => ['count' => $item->count, 'total' => $item->total]])
                ->toArray(),
        ];
    }

    public function updatedDateTo($value): void
    {
        if ($this->date_from && $value && $value < $this->date_from) {
            $this->date_to = $this->date_from;
            Notification::make()
                ->title('Invalid date range')
                ->body('The end date must be on or after the start date.')
                ->danger()
                ->send();
        }
    }

    public function updatedDateFrom($value): void
    {
        if ($value && $this->date_to && $this->date_to < $value) {
            $this->date_to = $value;
            Notification::make()
                ->title('Invalid date range')
                ->body('The end date must be on or after the start date.')
                ->danger()
                ->send();
        }
    }
}
