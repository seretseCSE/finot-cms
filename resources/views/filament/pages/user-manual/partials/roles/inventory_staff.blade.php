<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            <strong>Inventory Staff</strong> are the storekeepers. You keep the count of church property honest.
            You do not handle cash, members, or classes.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Add, edit, and organize inventory items</li>
            <li>Record inventory movements and stock movements (in, out, transfer)</li>
            <li>Record losses</li>
            <li>See inventory analytics and reports</li>
            <li>Manage related documents</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>See or record contributions, donations, or bank accounts</li>
            <li>Open member records, education, tours, or charity programs</li>
            <li>Send church-wide messages or book facilities (unless another role is added)</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Something arrives</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>If the item is new, create it under <strong>Inventory Items</strong> (name, category, unit, location, minimum stock).</li>
                    <li>Record a <strong>Stock Movement</strong> of type In. Enter quantity, date, and reason (purchase, donation).</li>
                    <li>Check that the quantity on the item now matches what is on the shelf.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Something is issued</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Record a movement of type Out. Say who received it and why.</li>
                    <li>If the item drops below the minimum, it appears as low stock on your dashboard.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Something is lost or damaged</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create a <strong>Loss Record</strong> with quantity, reason, and date.</li>
                    <li>The stock count is reduced so the books match the shelf.</li>
                    <li>Tell Nibret Hisab Head if the loss is large.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>A purchase must also appear in the bank book → Nibret Hisab Head or Finance Head</li>
            <li>Items were given as aid to a person → Charity Head should also record the distribution</li>
        </ul>
    </x-filament::section>
</div>
