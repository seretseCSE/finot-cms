<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Your job</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            You take and lock <strong>attendance</strong> for classes and teachers.
            You do not enroll students or enter exam scores.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create attendance sessions and mark Present / Absent / Late / Excused</li>
            <li>Lock (and unlock) sessions when finished</li>
            <li>Record teacher attendance and assign a substitute</li>
            <li>Open attendance summary and teacher-attendance reports</li>
            <li>View classes, subjects, and enrollments (read-only)</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create batches, enroll, promote, or change batch</li>
            <li>Enter assessment scores or run roster reports</li>
            <li>Create members or handle withdrawals</li>
            <li>Finance or other church offices</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Take student attendance</x-slot>
        <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Open Student Attendance (or Create Attendance Session).</li>
            <li>Choose class, date, and session type.</li>
            <li>Mark each student, save, then lock the session.</li>
        </ol>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need help</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Student missing from the list → Education Head</li>
            <li>Wrong class or batch year → Education Head</li>
            <li>Scores or promotions → Education Head</li>
        </ul>
    </x-filament::section>
</div>
