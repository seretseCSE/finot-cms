<div class="space-y-6 mb-10">
    <x-filament::section>
        <x-slot name="heading">Cross-office flows (trainers only)</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Use this when explaining hand-offs. Regular staff do not see this page — only their role guide.
        </p>

        <div class="space-y-6">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">School marks</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li>Education Head creates batch, semester, offerings, and assessments; activates the semester.</li>
                    <li>Data Encoder (or Head) saves scores. Students see them immediately — no approval step.</li>
                    <li>Education Head computes results when an official roster is needed.</li>
                </ol>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Student withdrawal</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li>Student applies.</li>
                    <li>Education Head or Admin approves or rejects.</li>
                    <li>HR Head finalizes — only then is enrollment Withdrawn.</li>
                </ol>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">New member</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li>HR (or Education / Admin) creates the member and department.</li>
                    <li>Education enrolls into batch + class if they will study.</li>
                    <li>Other offices (Mezmur, Finance, …) use that member for their own work.</li>
                </ol>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Who to ask</h3>
                <div class="overflow-x-auto mt-2">
                    <table class="min-w-full text-sm text-left text-gray-600 dark:text-gray-400">
                        <thead class="text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-2 pr-4 font-semibold">Need</th>
                                <th class="py-2 font-semibold">Ask</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr><td class="py-2 pr-4">Member record</td><td>HR Head</td></tr>
                            <tr><td class="py-2 pr-4">Batch, enrollment, scores setup</td><td>Education Head</td></tr>
                            <tr><td class="py-2 pr-4">Attendance</td><td>Education Monitor</td></tr>
                            <tr><td class="py-2 pr-4">Type scores</td><td>Data Encoder</td></tr>
                            <tr><td class="py-2 pr-4">Finalize withdrawal</td><td>HR Head</td></tr>
                            <tr><td class="py-2 pr-4">Payments</td><td>Finance Head</td></tr>
                            <tr><td class="py-2 pr-4">Unlock account / roles</td><td>Admin or Super Admin</td></tr>
                            <tr><td class="py-2 pr-4">System settings / backup</td><td>Super Admin</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-filament::section>
</div>
