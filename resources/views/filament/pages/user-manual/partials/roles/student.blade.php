<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            A <strong>Student</strong> is an enrolled member with a phone login.
            They use the <strong>student portal</strong> only. They never enter the admin office.
            This page is for staff who train students or help a parent.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What a student can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>See <strong>their own</strong> exam results</li>
            <li>See <strong>their own</strong> attendance</li>
            <li>Read announcements</li>
            <li>Browse the library</li>
            <li>Download songs for offline use</li>
            <li>Apply to withdraw from a class</li>
            <li>Export their own documents</li>
            <li>Update their profile</li>
            <li>Log in and log out</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What a student cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Open anyone else’s marks, attendance, or files</li>
            <li>Enter the admin office or any staff menu</li>
            <li>Approve their own withdrawal — Education Head decides, HR finishes</li>
            <li>Change class, teacher, or enrollment themselves</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">First login</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Staff create the member and enroll them. A student login is provisioned.</li>
                    <li>The student signs in with their phone.</li>
                    <li>They must change the temporary password. Until they do, they stay on the profile page.</li>
                    <li>After that they use the portal home: results, attendance, library, announcements.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Leaving a class</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>From the portal, apply for withdrawal. Give a reason. They can only apply for their own enrollment.</li>
                    <li>They cannot apply again while another request is already pending or education-approved.</li>
                    <li>Education Head approves or rejects. HR finalizes if approved.</li>
                    <li>They can print the withdrawal paper when they are allowed to.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When staff must help</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>No login or forgotten password → Admin or Super Admin</li>
            <li>Wrong class → Education Head</li>
            <li>Results not showing → Data Encoder may not have submitted, or Education Head has not approved</li>
            <li>Withdrawal stuck after Education approved → HR Head</li>
        </ul>
    </x-filament::section>
</div>
