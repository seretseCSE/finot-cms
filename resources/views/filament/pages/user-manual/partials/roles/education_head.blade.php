<div class="space-y-6">
    <x-filament::section>
        <x-slot name="heading">Who this person is</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
            The <strong>Education Head</strong> runs the church school: years, classes, teachers, students,
            attendance, and exam results. You may create or update a member when you register a student.
            You approve marks and the first step of a withdrawal. HR finishes the withdrawal.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you can do</x-slot>
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">The school structure</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Create academic years and turn them on or off</li>
                    <li>Manage classes, subjects, courses, and lessons</li>
                    <li>Manage enrollments and promotions, including promoting many students at once</li>
                    <li>Enroll or remove students</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Teachers and attendance</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>Add teachers (including external), assign them to members, classes, and subjects</li>
                    <li>Update or remove assignments and see assignment history</li>
                    <li>Assign a substitute</li>
                    <li>See teacher attendance rate</li>
                    <li>Open, lock, and unlock attendance sessions and records (including offline records)</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Marks, people, and files</h3>
                <ul class="list-disc ml-5 space-y-1 mt-1">
                    <li>View, enter, manage, and <strong>approve exam results</strong></li>
                    <li><strong>Approve or reject a student withdrawal</strong></li>
                    <li>Create and update members; see the <strong>education part</strong> of a member’s timeline</li>
                    <li>Upload library resources and manage library categories</li>
                    <li>Education reports: teacher attendance, student progress, class performance, attendance summary</li>
                    <li>Send messages and view or book a facility</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">What you cannot do</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>See the finance part of a member’s timeline, or record offerings</li>
            <li><strong>Finalize</strong> a withdrawal — HR Head does that after you approve</li>
            <li>Manage inventory, tours, charity programs, or the public website</li>
            <li>Change system settings</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">How the work flows</x-slot>
        <div class="space-y-4">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Start a school year</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create the <strong>Academic Year</strong> (name, start, end) and mark it active.</li>
                    <li>Create or update <strong>Classes</strong> and <strong>Subjects</strong>.</li>
                    <li>Add <strong>Teachers</strong> and assign them to class and subject.</li>
                    <li>Enroll students. If the person is not a member yet, create the member first (or ask HR).</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Enroll a student</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Go to <strong>Student Enrollments</strong> → <strong>New Enrollment</strong>.</li>
                    <li>Select the member, class, and year. Set the date and status.</li>
                    <li>At year end, use <strong>Bulk Promote</strong> to move a class forward.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Attendance (you or the Monitor)</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>Create an attendance session (class, date, morning / afternoon / full day).</li>
                    <li>Mark each student Present, Absent, Late, or Excused.</li>
                    <li>Mark teacher attendance the same way.</li>
                    <li>Lock the session when it is finished so it is not changed by accident.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Approve exam marks</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>The Data Encoder (or you) types the marks and submits the list.</li>
                    <li>Open the submitted marklist. Check a few scores.</li>
                    <li>Approve. Students can then see their own results.</li>
                    <li>If something is wrong after approval, reopen it with a written reason (at least 10 characters) and have the scores fixed.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A student wants to leave</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                    <li>The student applies from the dashboard. You are notified.</li>
                    <li>Approve or reject. If you reject, they stay enrolled.</li>
                    <li>If you approve, HR is notified. They must finalize before the enrollment is actually closed.</li>
                </ol>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">When you need someone else</x-slot>
        <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <li>Daily attendance only → Education Monitor</li>
            <li>Typing a long marklist → Data Encoder, then you approve</li>
            <li>Withdrawal last step → HR Head</li>
            <li>Church-wide member register cleanup → HR Head</li>
        </ul>
    </x-filament::section>
</div>
