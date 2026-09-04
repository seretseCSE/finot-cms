<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Your job</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            You run the school side of Finote: <strong>batches</strong> (for example Class of 2026),
            program years, semesters, subject offerings, enrollments, promotions, and reports.
            Encoders type scores; you set up the structure and can compute official roster totals anytime.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create and close <strong>Batches</strong>; edit named program years</li>
            <li>Create semesters under a batch year; <strong>Activate</strong> or <strong>Close</strong> a semester</li>
            <li><strong>Compute results</strong> anytime for roster totals, averages, and ranks</li>
            <li>Assign subjects and teachers (subject offerings) and create assessments</li>
            <li>Enroll students into a batch + batch year + class</li>
            <li>Use the <strong>Promotion board</strong> (Results or Student movement) to pass a whole class or set Pass/Fail per student after computing results</li>
            <li>Pass keeps the same batch and moves to the next class; <strong>Fail (leave batch)</strong> moves to another batch at the same year level (passed subjects stay as credits)</li>
            <li>Use <strong>Attendance</strong>, <strong>Results</strong>, and <strong>Class Work</strong> in the sidebar</li>
            <li>Open Marklist report and Roster report; grading scale; attendance and other education reports</li>
            <li>Approve or reject a student withdrawal (HR finalizes)</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Finalize a withdrawal — that is HR Head</li>
            <li>Contributions, inventory, donations, finance, tours, or system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Typical school year flow</x-slot>
        <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-2">
            <li>Create a <strong>Batch</strong> (Class of 2026). Finote creates Year 1…N for the tenure.</li>
            <li>Add a <strong>Semester</strong> on the current batch year and <strong>Activate</strong> it.</li>
            <li>Create <strong>Subject offerings</strong> (subject + teacher + class) and assessments (midterm, final, …).</li>
            <li>Enroll students into the batch, batch year, and class.</li>
            <li>Encoders enter scores on the active semester. Scores are live when saved — no approval queue.</li>
            <li>When you need an official sheet, click <strong>Compute results</strong>, then open the Roster report.</li>
            <li>Close the semester when it ends; activate the next one. On the <strong>Promotion board</strong>, load the batch and class, accept suggestions or set Pass/Fail, then Apply promotions.</li>
            <li>When the tenure finishes, close the batch.</li>
        </ol>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Fail (leave batch)</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            Move the student to another batch at the <strong>same program year</strong> (Year 2 → Year 2).
            Passed subject scores stay on their record. The new batch may have different Year 2 subjects —
            matching passed subjects count as transferred; new subjects must be taken.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need help</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Daily attendance → Education Monitor</li>
            <li>Long score entry → Data Encoder</li>
            <li>Withdrawal final step → HR Head</li>
        </ul>
    </x-filament::section>
</div>
