<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Mezmur Head</strong> owns the song book and rehearsals.
            You decide what is sung, when the choir meets, and who attended.
            You can see members and departments so you know who belongs to Mezmur.
            A Worship Monitor helps you; they cannot see the full member list or send broadcasts.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Full control of songs, song categories, and subcategories</li>
            <li>Schedule rehearsals and take rehearsal attendance</li>
            <li>Control whether worship media is visible</li>
            <li>Record rehearsal attendance offline</li>
            <li>View members and departments</li>
            <li>Manage documents</li>
            <li>View reports (including teacher reports if you need them for shared spaces)</li>
            <li>Send messages and view or book a facility</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create or edit member personal records (ask HR)</li>
            <li>Record money, inventory, classes, or exam marks</li>
            <li>Run tours, charity, or the public news site (AV Head publishes church-wide content)</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Add a song</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Songs</strong> → <strong>New Song</strong>.</li>
                    <li>Enter title, lyrics, and author.</li>
                    <li>Assign a category (worship, thanksgiving, communion, Lent, and so on) and optional subcategory.</li>
                    <li>Set language and key if you use them. Save.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Run a rehearsal</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create a <strong>Rehearsal</strong>: date, time, place, choir or group.</li>
                    <li>Add the songs you will practise from the song library.</li>
                    <li>If you need a room, book the facility.</li>
                    <li>After the rehearsal, mark attendance. You can do this offline and sync later.</li>
                    <li>Decide which related media should be visible.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>A singer is not in the member list → HR Head</li>
            <li>A recording should appear on the public site → AV Head</li>
            <li>Daily song filing without broadcasts → Worship Monitor can help</li>
        </ul>
    </x-filament::section>
</div>
