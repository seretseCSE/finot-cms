<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>HR Head</strong> is the church membership office.
            If a person is not in the member list, almost no other office can work with them correctly.
            You own the register: add people, fix details, assign groups, manage parents, and close a student
            withdrawal after Education has approved it.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Members</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Add, edit, search, and export members</li>
                    <li>Change a member’s status</li>
                    <li>See a member’s timeline</li>
                    <li>Assign people to groups, including many at once, or remove them from groups</li>
                    <li>Work across departments for membership (you are the church-wide register)</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Families and files</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Create, update, and remove parent records</li>
                    <li>Upload and manage documents</li>
                    <li><strong>Finalize a student withdrawal</strong> after Education has approved it</li>
                    <li>Open the teacher attendance report page</li>
                    <li>Send messages and view or book a facility</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Record contributions, donations, or bank transactions</li>
            <li>Manage inventory</li>
            <li>Create classes, enroll students, or enter exam marks</li>
            <li>Approve exam results (Education Head / Admin)</li>
            <li>Approve a withdrawal (you only do the last step, after Education)</li>
            <li>Run tours, charity programs, songs, or the website</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Register a new member</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Members</strong> → <strong>New Member</strong>.</li>
                    <li>Enter full name, date of birth, gender, and marital status.</li>
                    <li>Enter phone as 9 digits after +251 (example: 911234567).</li>
                    <li>Add address, emergency contact, and spiritual information on the other tabs.</li>
                    <li>Choose the correct <strong>department</strong>. This decides who else can see them.</li>
                    <li>Save. Then assign groups and, if needed, parent records.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Put people in groups</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create the group under <strong>Groups</strong> if it does not exist (leader, schedule, department).</li>
                    <li>Use <strong>Group Assignments</strong> to add or remove members and give them a role in the group.</li>
                    <li>For many people at once, use bulk assign.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Finish a student withdrawal</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Wait until Education Head or Admin has approved the request. You will be notified.</li>
                    <li>Open the withdrawal. Confirm the reason and the date it should take effect.</li>
                    <li>Finalize. The enrollment becomes Withdrawn. Do not finalize a request that is still Pending.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Student must leave a class but Education has not approved yet → Education Head</li>
            <li>The person should also be enrolled in school → Education Head after you create the member</li>
            <li>A user account (login) is needed, not just a member record → Admin or Super Admin</li>
        </ul>
    </x-filament::section>
</div>
