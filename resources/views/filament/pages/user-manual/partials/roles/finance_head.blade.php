<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Finance Head</strong> keeps the church books: offerings, donations, bank accounts,
            and the reports leadership uses. You look up members so you can attach a payment to the right person.
            You do not change their personal details or run the store.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Money in and out</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Record and manage contributions; set expected contribution amounts</li>
                    <li>Record donations</li>
                    <li>Record financial transactions (deposit, withdrawal, transfer)</li>
                    <li>Manage bank accounts</li>
                    <li>Update a fundraising total</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Reports and people</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Financial statements, financial audit trail, financial analytics</li>
                    <li>Donation report, contribution report, contribution form</li>
                    <li>View and export members; see the <strong>finance part</strong> of a member’s timeline</li>
                    <li>View charity reports and tour reports (the money side, not running those programs)</li>
                    <li>Documents, messages, and facility booking</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create or edit member personal details, groups, or parents</li>
            <li>Manage inventory, stock, or losses</li>
            <li>Run education, songs, tours, or aid distributions</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Record a contribution</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Confirm the person is already a member. If not, send them to HR first.</li>
                    <li>Go to <strong>Contribution Form</strong>.</li>
                    <li>Filter by academic year and group.</li>
                    <li>Check the month for each member who paid, then save.</li>
                    <li>Amounts come from Contribution Settings. The payment appears on reports.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Set expected amounts</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Use <strong>Contribution Settings</strong> so staff type the same figures every month.
                    This also helps the outstanding-contributions report.
                </p>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Record a donation</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Donations</strong> → <strong>New Donation</strong>.</li>
                    <li>Enter the donor, type (general fund, building, and so on), amount, date, and method.</li>
                    <li>Save. Review later under <strong>Donation Report</strong>.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Bank and monthly close</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Keep <strong>Bank Accounts</strong> up to date.</li>
                    <li>Record each deposit, withdrawal, or transfer under <strong>Financial Transactions</strong>.</li>
                    <li>At month end, open Financial Overview, Statement, and Audit Trail.</li>
                    <li>Use Contribution Form to see who has and has not given for the period.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Reports you will use</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li><strong>Contribution Form</strong> — members across periods</li>
            <li><strong>Contribution Report</strong></li>
            <li><strong>Outstanding Contributions</strong> — unpaid</li>
            <li><strong>Donation Report</strong></li>
            <li><strong>Financial Overview</strong>, <strong>Statement</strong>, and <strong>Audit Trail</strong></li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Payer is not a member yet → HR Head</li>
            <li>An item was bought or issued from the store → Inventory Staff or Nibret Hisab Head</li>
            <li>Aid was given to a person (not a bank transaction) → Charity Head</li>
        </ul>
    </x-filament::section>
</div>
