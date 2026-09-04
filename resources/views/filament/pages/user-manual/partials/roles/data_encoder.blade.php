<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Your job</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            You enter assessment scores for classes on the <strong>active semester</strong>.
            When you save, students can see those scores. There is no separate approval step.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Open <strong>Record assessments</strong> (or Record Marks) for an active semester</li>
            <li>Enter numeric scores (and optional rubric fields where shown)</li>
            <li>Save — marks are live immediately</li>
            <li>Update your profile and open this guide</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create batches, enroll students, or change class lists</li>
            <li>Activate or close a semester</li>
            <li>Create subject offerings or assessments (Education Head does that)</li>
            <li>Run roster / school reports</li>
            <li>Finance, members, tours, or other offices</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How to enter scores</x-slot>
        <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Confirm Education Head has set the semester to <strong>Active</strong> and created the assessments.</li>
            <li>Open <strong>Record assessments</strong>.</li>
            <li>Pick the active semester, the subject offering, and the assessment (for example Midterm).</li>
            <li>Load the roster. Type each student’s score. Mark absent if needed.</li>
            <li>Click <strong>Save scores</strong>. Students see updates right away.</li>
        </ol>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
            If the semester is closed, you cannot save. Ask Education Head to reopen or activate the right semester.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need help</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Student missing from the roster → Education Head (enrollment)</li>
            <li>No assessment to select → Education Head (create offerings / assessments)</li>
            <li>Semester not active → Education Head</li>
        </ul>
    </x-filament::section>
</div>
