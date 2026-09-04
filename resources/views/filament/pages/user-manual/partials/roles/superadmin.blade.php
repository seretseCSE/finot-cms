<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Super Admin</strong> is the owner of the platform itself — usually one or two trusted
            IT or leadership people. They can open every screen and override any blocked step.
            This is not a daily department clerk. Do not use this account to register members or record offerings.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">People and access</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Create user accounts, assign roles, and attach a department</li>
                    <li>Lock or unlock an account, reset a password, turn a user on or off</li>
                    <li>See who is logged in and end a session</li>
                    <li>See audit logs: who changed what, and when</li>
                    <li>Use emergency override when someone is stuck</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">The church system</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Change Global Church Settings (name, address, phone prefix +251, currency, branding)</li>
                    <li>Create and restore backups</li>
                    <li>Watch System Health (disk, memory, queues)</li>
                    <li>Read the Error Log Viewer when something breaks</li>
                    <li>Export audit logs from the Audit Logs page</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Every department</h3>
                <p class="mt-1">
                    You can do anything any other role can do: members, money, inventory, education, marks,
                    withdrawals, imports, songs, media, charity, tours, events, and messages.
                    You see <strong>all departments</strong>, not only one.
                </p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you should not do day to day</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Do not type daily contributions or take class attendance on this account</li>
            <li>Do not share this password. Create an Admin account for ordinary church management</li>
            <li>Restoring a backup replaces current data and logs everyone out — treat it as last resort</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Give someone a new login</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Users</strong> → <strong>New User</strong>.</li>
                    <li>Enter name, email, and phone (9 digits after +251).</li>
                    <li>Assign the <strong>department</strong> they work in. This controls whose members they will see.</li>
                    <li>Choose one or more <strong>roles</strong> (for example Finance Head).</li>
                    <li>Save. They receive a temporary password and must change it on first login.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Keep the system safe</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open <strong>System Health</strong> if the office says the site is slow.</li>
                    <li>Open <strong>Error Log Viewer</strong> if a screen fails.</li>
                    <li>Create a backup from <strong>Backup &amp; Restore</strong> at least weekly, and before a big change.</li>
                    <li>Review audit logs monthly.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Unblock a department</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Confirm the person has the right role and department.</li>
                    <li>If they cannot see a member, check the member’s department.</li>
                    <li>If Education needs help with enrollments or scores, you can step in. Withdrawals still end with HR finalize.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Tips</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>One person can hold more than one role if they truly do both jobs.</li>
            <li>Use <strong>Manage Active Sessions</strong> (profile menu) to end a forgotten or suspicious login.</li>
            <li>Print this whole User Manual from this page when you train new heads.</li>
        </ul>
    </x-filament::section>
</div>
