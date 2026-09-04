<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Admin</strong> runs the church’s daily office across departments.
            They can manage people, money, school, worship, media, charity, tours, and events.
            They cannot open the technical back room (system settings, backups, error logs).
            They also do not run the store — that belongs to Nibret Hisab / Inventory Staff.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">People</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Create and manage users, assign roles, lock or unlock accounts, reset passwords</li>
                    <li>See who is logged in</li>
                    <li>Full member and group work, including parents</li>
                    <li>See members from every department</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Money and reports</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Contribution settings and form, donations, bank accounts, and financial transactions</li>
                    <li>Financial statements, audit trail, analytics</li>
                    <li>Donation, contribution, and contribution-form reports</li>
                    <li>Charity and beneficiary reports; tour reports</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">School</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Batches, academic years, semesters, classes, subjects, offerings, enrollments</li>
                    <li>Enter assessment scores (live on save); compute roster results; open reports</li>
                    <li>Promote or fail/change batch; approve student withdrawals (HR finalizes)</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Church life and content</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Songs, rehearsals, media, blogs, announcements, FAQs, documents</li>
                    <li>Schedule when content is published</li>
                    <li>Beneficiaries and aid; tours and passengers; events and registrations</li>
                    <li>Create or update fundraising totals</li>
                    <li>Manage departments and contact messages</li>
                    <li>Send messages to a group or the whole church</li>
                    <li>View, manage, and book facilities</li>
                    <li>See visitor analytics</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Change Global Church Settings, backups, error logs, or system health</li>
            <li>Manage inventory items, stock movements, or loss records</li>
            <li><strong>Finalize</strong> a student withdrawal — that last step belongs to HR Head</li>
            <li>Apply for a withdrawal as if you were the student (students do that from My Learning)</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A typical week</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open the dashboard: members, income, expenses, attendance trend.</li>
                    <li>Help a department that is stuck (wrong role, missing member, locked user).</li>
                    <li>If Education Head is away, help with enrollments, scores, or semester activation.</li>
                    <li>If a withdrawal is waiting, approve or reject it; tell HR to finalize if you approved.</li>
                    <li>Review contact messages and recent transactions.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Add a staff user</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Users</strong> → <strong>New User</strong>.</li>
                    <li>Enter name, email, phone, and department.</li>
                    <li>Assign the correct role. Save. They get an invitation with a temporary password.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Backups, settings, or a broken server → Super Admin</li>
            <li>Store / stock counts → Inventory Staff or Nibret Hisab Head</li>
            <li>Final step of a withdrawal → HR Head</li>
        </ul>
    </x-filament::section>
</div>
