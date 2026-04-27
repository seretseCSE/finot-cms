<div x-data="ethiopianDatePicker({{ json_encode([
    'name' => $name,
    'value' => $value,
    'id' => $id,
    'excludePagume' => $excludePagume,
    'showAllMonths' => $showAllMonths,
    'locale' => $locale,
    'placeholder' => $placeholder,
    'required' => $required,
    'months' => $months,
    'years' => $years,
    'days' => $days,
    'currentDate' => $currentEthiopianDate,
])])" class="ethiopian-date-picker">
    
    <!-- Hidden input to store the value -->
    <input 
        type="hidden" 
        :name="name" 
        x-model="selectedDate" 
        :id="id"
        :required="required"
    />
    
    <!-- Display input -->
    <div class="relative">
        <input 
            type="text" 
            readonly
            x-model="displayValue"
            :placeholder="placeholder"
            @click="showPicker = !showPicker"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
            :class="{ 'ring-red-500 border-red-500': required && !selectedDate }"
        />
        
        <!-- Calendar icon -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>
    
    <!-- Picker dropdown -->
    <div 
        x-show="showPicker" 
        @click.away="showPicker = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute z-50 mt-1 p-4 bg-white border border-gray-300 rounded-md shadow-lg"
    >
        <div class="grid grid-cols-3 gap-4">
            <!-- Day selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                <select 
                    x-model="selectedDay" 
                    @change="updateDate()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">--</option>
                    @foreach($days as $day)
                        <option value="{{ $day }}">{{ $day }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Month selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select 
                    x-model="selectedMonth" 
                    @change="updateDate()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">--</option>
                    @foreach($months as $key => $month)
                        <option value="{{ $key }}">{{ $month }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Year selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select 
                    x-model="selectedYear" 
                    @change="updateDate()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">--</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <!-- Quick actions -->
        <div class="mt-4 flex justify-between">
            <button 
                type="button"
                @click="selectToday()"
                class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md"
            >
                Today ({{ $currentEthiopianDate['day'] }} {{ $currentEthiopianDate['month'] }} {{ $currentEthiopianDate['year'] }})
            </button>
            
            <button 
                type="button"
                @click="clearDate()"
                class="px-3 py-1 text-sm bg-red-100 hover:bg-red-200 text-red-700 rounded-md"
            >
                Clear
            </button>
        </div>
    </div>
</div>

<script>
function ethiopianDatePicker(config) {
    return {
        name: config.name,
        value: config.value,
        id: config.id,
        excludePagume: config.excludePagume,
        showAllMonths: config.showAllMonths,
        locale: config.locale,
        placeholder: config.placeholder,
        required: config.required,
        months: config.months,
        years: config.years,
        days: config.days,
        currentDate: config.currentDate,
        showPicker: false,
        selectedDay: '',
        selectedMonth: '',
        selectedYear: '',
        selectedDate: '',
        displayValue: '',
        
        init() {
            // Initialize from value if provided
            if (this.value) {
                const parts = this.value.split('-');
                if (parts.length === 3) {
                    this.selectedDay = parts[0];
                    this.selectedMonth = parts[1];
                    this.selectedYear = parts[2];
                    this.selectedDate = this.value;
                    this.updateDisplayValue();
                }
            }
        },
        
        updateDate() {
            if (this.selectedDay && this.selectedMonth && this.selectedYear) {
                this.selectedDate = `${this.selectedDay}-${this.selectedMonth}-${this.selectedYear}`;
                this.updateDisplayValue();
            } else {
                this.selectedDate = '';
                this.displayValue = '';
            }
        },
        
        updateDisplayValue() {
            if (this.selectedDay && this.selectedMonth && this.selectedYear) {
                const monthName = this.months[this.selectedMonth] || this.selectedMonth;
                this.displayValue = `${this.selectedDay} ${monthName} ${this.selectedYear}`;
            } else {
                this.displayValue = '';
            }
        },
        
        selectToday() {
            this.selectedDay = this.currentDate.day;
            this.selectedMonth = this.currentDate.month;
            this.selectedYear = this.currentDate.year;
            this.updateDate();
            this.showPicker = false;
        },
        
        clearDate() {
            this.selectedDay = '';
            this.selectedMonth = '';
            this.selectedYear = '';
            this.selectedDate = '';
            this.displayValue = '';
            this.showPicker = false;
        }
    }
}
</script>
