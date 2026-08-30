<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Education Monitor</strong> takes and watches attendance.
            You do not run the school. You open a session, mark who is present, lock it, and read the reports.
            You may assign a substitute teacher when someone is away.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create and manage attendance sessions (including resolving sync conflicts)</li>
            <li>Mark, lock, and unlock attendance records; record attendance offline</li>
            <li>Assign a substitute teacher</li>
            <li><strong>View</strong> academic years, classes, subjects, enrollments, teachers, and members</li>
            <li>Open student attendance, attendance-summary, and teacher-attendance reports</li>
            <li>View facilities (you cannot book them)</li>
            <li>Work with documents for attendance</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Create classes, enroll students, or promote them</li>
            <li>Add or edit teachers (except assigning a substitute)</li>
            <li>Enter or approve exam marks</li>
            <li>Create or change member records</li>
            <li>Commit imports or handle withdrawals</li>
            <li>Record money or inventory</li>
            <li>Send broadcasts</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Take student attendance</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Create Attendance Session</strong> (or Student Attendance).</li>
                    <li>Choose the class, date, and session type (morning, afternoon, full day).</li>
                    <li>Create the session. The class list opens.</li>
                    <li>For each student mark Present, Absent, Late, or Excused. Add a note if needed.</li>
                    <li>Save, then lock the session so it cannot be changed by accident.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Teacher is absent</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Mark the teacher absent on <strong>Teacher Attendance</strong>.</li>
                    <li>Assign a substitute for that class and subject.</li>
                    <li>Tell Education Head if this happens often — they manage the teacher list.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">After class</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Open <strong>Attendance Summary</strong> if a class looks weak.</li>
                    <li>If you recorded attendance on a phone without network, sync later and resolve any conflicts.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>A student is missing from the list → Education Head (enrollment)</li>
            <li>Wrong class or year → Education Head</li>
            <li>Marks, promotions, or withdrawals → Education Head</li>
        </ul>
    </x-filament::section>
</div>
