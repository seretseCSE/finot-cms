<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Tour Head</strong> runs church trips from plan to attendance.
            You create the tour, register passengers, confirm them, take attendance, and report.
            You do not run charity or the main finance books.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create and update tours</li>
            <li>View registrations; register passengers; confirm a registration</li>
            <li>Create tour attendance and mark who actually travelled</li>
            <li>Use the call button and tour reports</li>
            <li>Manage tour passengers and tour attendances</li>
            <li>Documents, messages, and facility booking</li>
            <li>Open the tour report page</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Manage beneficiaries or aid distributions</li>
            <li>Record church-wide contributions or bank accounts</li>
            <li>Change member personal records (passengers are chosen from members that already exist)</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Plan a tour</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Tours</strong> → <strong>New Tour</strong>.</li>
                    <li>Enter name, destination, description, start and end dates, departure time.</li>
                    <li>Enter cost per passenger if there is a fee, and the maximum number of seats.</li>
                    <li>Save. Book a meeting room if you need a briefing.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Register passengers</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open the tour → <strong>Passengers</strong> → <strong>Add Passenger</strong>.</li>
                    <li>Select a member. If they are not in the list, HR must create them first.</li>
                    <li>Record payment status if you collect a fee. Confirm the registration when it is sure.</li>
                    <li>Use the call button to reach people who have not confirmed.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">On the trip</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create tour attendance for the date.</li>
                    <li>Check off who actually travelled (not only who registered).</li>
                    <li>After return, open <strong>Tour Report</strong> for counts, attendance, and any revenue.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Passenger is not a member → HR Head</li>
            <li>Tour fees should hit the church books → Finance Head or Nibret Hisab Head</li>
            <li>The same office also gives aid → use Revenue &amp; Charity Head, or work with Charity Head</li>
        </ul>
    </x-filament::section>
</div>
