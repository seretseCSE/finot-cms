<?php

namespace App\Filament\Pages\Education;

use App\Models\GradingScale;
use App\Models\GradingScaleBand;
use App\Support\RoleGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradingScalePage extends Page
{
    protected static ?string $title = 'Grading Scale';

    protected string $view = 'filament.pages.education.grading-scale';

    protected static ?int $navigationSort = 20;

    public string $scaleName = 'Default university scale';

    public array $bands = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function getNavigationLabel(): string
    {
        return 'Grading Scale';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return RoleGate::can('page.grading-scale');
    }

    public function mount(): void
    {
        $scale = GradingScale::defaultScale();
        $this->scaleName = $scale?->name ?? 'Default university scale';
        $this->bands = $scale?->bands->map(fn (GradingScaleBand $band) => [
            'id' => $band->id,
            'label' => $band->label,
            'min_score' => $band->min_score,
            'max_score' => $band->max_score,
        ])->values()->all() ?? $this->defaultBands();
    }

    public function addBand(): void
    {
        $this->bands[] = [
            'id' => null,
            'label' => '',
            'min_score' => 0,
            'max_score' => 0,
        ];
    }

    public function removeBand(int $index): void
    {
        unset($this->bands[$index]);
        $this->bands = array_values($this->bands);
    }

    public function save(): void
    {
        $this->validate([
            'scaleName' => 'required|string|max:120',
            'bands' => 'required|array|min:1',
            'bands.*.label' => 'required|string|max:16',
            'bands.*.min_score' => 'required|integer|min:0|max:100',
            'bands.*.max_score' => 'required|integer|min:0|max:100',
        ]);

        foreach ($this->bands as $i => $band) {
            if ((int) $band['min_score'] > (int) $band['max_score']) {
                throw ValidationException::withMessages([
                    "bands.$i.min_score" => 'Minimum must be less than or equal to maximum.',
                ]);
            }
        }

        DB::transaction(function (): void {
            $scale = GradingScale::defaultScale() ?? new GradingScale([
                'is_default' => true,
                'created_by' => Auth::id(),
            ]);
            $scale->name = $this->scaleName;
            $scale->is_default = true;
            $scale->created_by = $scale->created_by ?: Auth::id();
            $scale->save();

            GradingScale::query()->where('id', '!=', $scale->id)->update(['is_default' => false]);

            $keep = [];
            foreach (array_values($this->bands) as $order => $band) {
                $record = ! empty($band['id'])
                    ? GradingScaleBand::query()->where('grading_scale_id', $scale->id)->find($band['id'])
                    : new GradingScaleBand(['grading_scale_id' => $scale->id]);

                $record ??= new GradingScaleBand(['grading_scale_id' => $scale->id]);
                $record->fill([
                    'grading_scale_id' => $scale->id,
                    'label' => $band['label'],
                    'min_score' => (int) $band['min_score'],
                    'max_score' => (int) $band['max_score'],
                    'sort_order' => $order + 1,
                ])->save();
                $keep[] = $record->id;
            }

            GradingScaleBand::query()
                ->where('grading_scale_id', $scale->id)
                ->whereNotIn('id', $keep)
                ->delete();
        });

        $this->mount();

        Notification::make()->title('Grading scale saved')->success()->send();
    }

    /**
     * @return list<array{id: null, label: string, min_score: int, max_score: int}>
     */
    protected function defaultBands(): array
    {
        return [
            ['id' => null, 'label' => 'A+', 'min_score' => 90, 'max_score' => 100],
            ['id' => null, 'label' => 'A', 'min_score' => 85, 'max_score' => 89],
            ['id' => null, 'label' => 'A-', 'min_score' => 80, 'max_score' => 84],
            ['id' => null, 'label' => 'B+', 'min_score' => 75, 'max_score' => 79],
            ['id' => null, 'label' => 'B', 'min_score' => 70, 'max_score' => 74],
            ['id' => null, 'label' => 'B-', 'min_score' => 65, 'max_score' => 69],
            ['id' => null, 'label' => 'C+', 'min_score' => 60, 'max_score' => 64],
            ['id' => null, 'label' => 'C', 'min_score' => 50, 'max_score' => 59],
            ['id' => null, 'label' => 'D', 'min_score' => 40, 'max_score' => 49],
            ['id' => null, 'label' => 'F', 'min_score' => 0, 'max_score' => 39],
        ];
    }
}
