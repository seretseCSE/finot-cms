<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Internal Relations Head</strong> keeps people connected: groups, parents, documents,
            and messages that come in through the church contact form.
            You are not the membership office. You usually <strong>view</strong> members and then place them
            in the right fellowship or service group. Creating a brand-new member from scratch belongs to HR
            (or Education / Admin).
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>View members</li>
            <li>Create and update groups; assign people to groups or change those assignments</li>
            <li>View, create, update, and delete parent records</li>
            <li>Full document management (upload, search, download, replace)</li>
            <li>Read contact messages from the public website</li>
            <li>View and update departments</li>
            <li>Open attendance-summary and teacher-attendance reports</li>
            <li>Delete a media item when something must be taken down</li>
            <li>Send messages and view or book a facility</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create a new member record from scratch (ask HR)</li>
            <li>Change member status the way HR does, or export the full register as HR does</li>
            <li>Record money, inventory, marks, tours, or aid</li>
            <li>Finalize a withdrawal</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Place someone in a group</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Confirm HR has already created the member.</li>
                    <li>Open <strong>Groups</strong>. Create the group if needed (name, leader, department).</li>
                    <li>Use <strong>Group Assignments</strong> to add the member and set their role in the group (leader, member, and so on).</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Answer a contact message</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open <strong>Contact Messages</strong>.</li>
                    <li>Read the message. If they need to become a member, send them to HR.</li>
                    <li>File any related letter or form under <strong>Documents</strong>.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Person is not in the system yet → HR Head</li>
            <li>They need a login (staff or student) → Admin or Super Admin</li>
            <li>They need to be enrolled in a class → Education Head</li>
        </ul>
    </x-filament::section>
</div>
