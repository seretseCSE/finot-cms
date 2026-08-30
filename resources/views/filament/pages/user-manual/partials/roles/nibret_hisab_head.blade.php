<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Nibret Hisab Head</strong> is Finance Head plus the store.
            You keep both the books and the property register. If the church has a separate Finance Head,
            you still share the money screens; the extra work that is yours is inventory.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Money (same as Finance Head)</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Contribution settings, contribution form, donations</li>
                    <li>Financial transactions and bank accounts</li>
                    <li>Financial statements, audit trail, analytics</li>
                    <li>Donation, contribution, and contribution-matrix reports</li>
                    <li>Update fundraising totals; view charity and tour reports</li>
                    <li>View members (so you can attach a payment)</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Property</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Add and update inventory items</li>
                    <li>Record inventory movements and stock movements</li>
                    <li>Record losses (damage, expiry, missing items)</li>
                    <li>See inventory analytics and reports</li>
                    <li>View beneficiaries (read only) when aid and stock meet</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Register beneficiaries or record aid distributions (Charity Head)</li>
            <li>Run education, songs, tours, or HR membership</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A typical day</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Record offerings and donations the same way Finance Head does.</li>
                    <li>When an item is bought, donated, or issued, open <strong>Inventory Items</strong> / <strong>Stock Movements</strong>.</li>
                    <li>Enter the item, quantity, in or out, date, and reason.</li>
                    <li>If something is damaged or missing, create a <strong>Loss Record</strong> so the count stays honest.</li>
                    <li>Check the dashboard for low stock and recent movements.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Add a store item</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Inventory Items</strong> → <strong>New Item</strong>.</li>
                    <li>Enter name, category, quantity, unit, and unit price.</li>
                    <li>Set location and a minimum stock level so you get an alert.</li>
                    <li>Save. Later movements adjust the quantity.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Daily stock counting only → Inventory Staff can do that without touching the books</li>
            <li>Aid given to a person → Charity Head</li>
            <li>New member needed before you can record their offering → HR Head</li>
        </ul>
    </x-filament::section>
</div>
