@php
    $helper = app(App\Helpers\EthiopianDateHelper::class);
    
    // Handle different date formats
    switch($format) {
        case 'short':
            // Short format: DD/MM/YYYY
            if (is_string($date)) {
                $ethiopian = $helper->toEthiopian($date);
                echo "{$ethiopian['day']}/{$ethiopian['month']}/{$ethiopian['year']}";
            } else {
                echo $formattedDate;
            }
            break;
            
        case 'long':
            // Long format: Day Month, Year (in Amharic)
            if (is_string($date)) {
                $ethiopian = $helper->toEthiopian($date);
                $monthNames = $locale === 'en' 
                    ? $helper::MONTH_NAMES_EN 
                    : $helper::MONTH_NAMES_AM;
                $monthKey = array_keys($monthNames)[$ethiopian['month'] - 1] ?? 0;
                $monthName = $monthNames[$monthKey] ?? $ethiopian['month'];
                
                echo "{$ethiopian['day']} {$monthName}, {$ethiopian['year']}";
            } else {
                echo $formattedDate;
            }
            break;
            
        case 'full':
            // Full format with day name: DayName, DD Month YYYY
            if (is_string($date)) {
                $ethiopian = $helper->toEthiopian($date);
                $carbon = \Carbon\Carbon::parse($date);
                $dayOfWeek = $carbon->dayOfWeek; // 0 = Sunday, 6 = Saturday
                $dayName = $helper->getAmharicDayName($dayOfWeek);
                
                $monthNames = $locale === 'en' 
                    ? $helper::MONTH_NAMES_EN 
                    : $helper::MONTH_NAMES_AM;
                $monthKey = array_keys($monthNames)[$ethiopian['month'] - 1] ?? 0;
                $monthName = $monthNames[$monthKey] ?? $ethiopian['month'];
                
                echo "{$dayName}, {$ethiopian['day']} {$monthName} {$ethiopian['year']}";
            } else {
                echo $formattedDate;
            }
            break;
            
        default:
            echo $formattedDate;
            break;
    }
@endphp
