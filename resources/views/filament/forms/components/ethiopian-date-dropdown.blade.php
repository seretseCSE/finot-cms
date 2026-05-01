@php
    use App\Helpers\EthiopianDateHelper;
    use Andegna\DateTime as EthiopianDateTime;

    $today = new EthiopianDateTime();
    $years = range($today->getYear() - $getYearsBefore(), $today->getYear() + $getYearsAfter());
    $months = EthiopianDateHelper::getAllEthiopianMonthNames();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            year: $wire.entangle('{{ $statePath }}.year'),
            month: $wire.entangle('{{ $statePath }}.month'),
            day: $wire.entangle('{{ $statePath }}.day'),

            get maxDay() {
                if (this.month == 13) {
                    return (this.year && this.year % 4 === 3) ? 6 : 5;
                }
                return 30;
            },

            init() {
                this.$watch('month', () => {
                    if (parseInt(this.day) > this.maxDay) {
                        this.day = null;
                    }
                });
                this.$watch('year', () => {
                    if (this.month == 13 && parseInt(this.day) > this.maxDay) {
                        this.day = null;
                    }
                });
            },
        }"
        x-id="['eth-date']"
        class="grid grid-cols-3 gap-3"
    >
        <div>
            <select
                x-model="day"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 sm:text-sm"
            >
                <option value="">Day</option>
                <template x-for="d in Array.from({length: maxDay}, (_, i) => i + 1)" :key="d">
                    <option :value="d" x-text="d"></option>
                </template>
            </select>
        </div>

        <div>
            <select
                x-model="month"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 sm:text-sm"
            >
                <option value="">Month</option>
                @foreach($months as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <select
                x-model="year"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 sm:text-sm"
            >
                <option value="">Year</option>
                @foreach($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </div>
    </div>
</x-dynamic-component>
