<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Charity Head</strong> looks after people who receive help and the aid that goes out.
            You may record donations that support that work, and look up members.
            You do not run tours (unless you also hold Revenue &amp; Charity Head) and you do not keep the main church books.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Full beneficiaries: register, update, and manage status</li>
            <li>Record aid distributions (money or goods)</li>
            <li>Charity reports and beneficiary reports</li>
            <li>View and create contributions; record donations</li>
            <li>View tour reports (read the money/trip picture, not run the tour)</li>
            <li>View and export members</li>
            <li>Open donation, charity, and beneficiary report pages</li>
            <li>Documents, messages, and facility booking</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create tours or manage passengers</li>
            <li>Manage inventory or bank accounts</li>
            <li>Run education or change member personal records</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Register someone who needs help</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Beneficiaries</strong> → <strong>New Beneficiary</strong>.</li>
                    <li>Enter name, age, gender, and address.</li>
                    <li>Choose type (individual, family, orphan, widow, and so on) and need (food, shelter, medical, education).</li>
                    <li>Set status (active, inactive, completed) and notes. Save.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Give aid</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Aid Distributions</strong> → <strong>New Distribution</strong>.</li>
                    <li>Select the beneficiary and the aid type (money, food, clothing, medical).</li>
                    <li>If money, enter the amount in ETB. If goods, describe items and quantity.</li>
                    <li>Set the date. Save.</li>
                    <li>After it is checked, lock the distribution so it cannot be edited by accident. Only Charity Head can unlock it.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Money that supports aid</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Record a donation earmarked for charity, or view/create a contribution if that is how the gift arrived.</li>
                    <li>Finance Head still owns the main books. Tell them if a large gift should also appear there.</li>
                    <li>Use Charity Report and Beneficiary Report for leadership.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Goods came from the store → Inventory Staff should record the stock out</li>
            <li>A fundraising trip brought the money → Tour Head or Revenue &amp; Charity Head</li>
            <li>The person should also be a church member → HR Head</li>
        </ul>
    </x-filament::section>
</div>
