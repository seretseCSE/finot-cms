<div class="space-y-6 mb-10">
    <x-filament::section>
        <x-slot name="heading">How the system works</x-slot>
        <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            <p>
                Finote is the church office. Each person who logs in is given a <strong>role</strong>.
                The role decides which menus they see, what they may change, and whose records they can open.
                You do not need every permission. You only need the work that belongs to your office.
            </p>
            <p>
                There are two doors after login:
            </p>
            <ul class="list-disc ml-5 space-y-1">
                <li><strong>Staff</strong> (every role except Student) enter the <strong>admin office</strong>.</li>
                <li><strong>Students</strong> enter the <strong>student portal</strong> only. They cannot open the admin office.</li>
            </ul>
            <p>
                If the account still has a temporary password, the person must change it before they can continue.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What happens when someone logs in</x-slot>
        <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-2">
            <li>The person signs in with their phone (and password).</li>
            <li>If this is the first time, they must set a new password.</li>
            <li>Staff land on a dashboard made for their role. Students land on the portal.</li>
            <li>The left menu shows only the work they are allowed to do.</li>
            <li>
                Super Admin and Admin can see members from <strong>every department</strong>.
                Other staff who belong to a department usually see only people from <strong>their own department</strong>,
                unless their job is church-wide membership (HR).
            </li>
        </ol>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Things almost every staff person can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Open their dashboard and read the numbers for their office</li>
            <li>Update their own profile</li>
            <li>Use Ethiopian dates</li>
            <li>Open this User Manual and other help</li>
            <li>Work with documents that belong to their area</li>
        </ul>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
            Most department heads can also send a message to their people and view or book a church facility.
            Students cannot do those things.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">The seven church departments</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Members and groups are tied to a department. Pick the right department when you register someone,
            or other offices will not see them correctly.
        </p>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Internal Relations</li>
            <li>Nibret ena Hisab (property and accounts)</li>
            <li>Education</li>
            <li>Revenue &amp; Charity</li>
            <li>Mezmur</li>
            <li>Foreign Affairs</li>
            <li>Kinetibeb</li>
        </ul>
    </x-filament::section>
</div>
