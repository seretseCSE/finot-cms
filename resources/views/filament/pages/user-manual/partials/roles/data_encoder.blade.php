<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Data Encoder</strong> types class and subject marklists. That is almost the whole job.
            You look up classes, subjects, and students so you can enter scores.
            You cannot approve those scores. Education Head (or Admin) must sign them off.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>View marklists</li>
            <li><strong>Record</strong> (type) marks</li>
            <li>View classes, subjects, and students</li>
            <li>Use Ethiopian dates</li>
            <li>Update your own profile and open help</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Approve results</li>
            <li>Change enrollments, teachers, or attendance</li>
            <li>Create members or commit an import</li>
            <li>See finance, tours, charity, inventory, songs, or media</li>
            <li>Send messages or book facilities</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Enter a marklist</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open the marklist page for the class and subject.</li>
                    <li>Confirm the term is the current one. You cannot type into an inactive term (Admin / Super Admin can override).</li>
                    <li>Type each student’s score. Save as you go if the list is long.</li>
                    <li>Submit the marklist when it is complete.</li>
                    <li>Tell Education Head it is ready. They approve it. Students then see their own results.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">If you made a mistake</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>If it is not submitted yet, just correct it.</li>
                    <li>If it is submitted or approved, ask Education Head to reopen it. They must write a reason.</li>
                    <li>You still cannot approve the corrected list. Someone else signs it off.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>A student is missing from the roster → Education Head (enrollment)</li>
            <li>Approval, reopen, or a closed term → Education Head or Admin</li>
            <li>Anything that is not marks → that department’s head</li>
        </ul>
    </x-filament::section>
</div>
