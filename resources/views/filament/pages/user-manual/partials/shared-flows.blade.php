<div class="space-y-6 mb-10">
    <x-filament::section>
        <x-slot name="heading">Work that crosses offices</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Some jobs cannot be finished by one person. One office starts the work; another office signs it off.
            These are the flows every staff member should know.
        </p>

        <div class="space-y-6">
            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A new person joins the church</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li><strong>HR Head</strong> (or Education Head / Admin) creates the member record and assigns the department.</li>
                    <li><strong>HR Head</strong> or <strong>Internal Relations</strong> puts them in the right groups and adds parent details if needed.</li>
                    <li>If they will study: <strong>Education Head</strong> enrolls them in a class.</li>
                    <li>If they will sing: <strong>Mezmur Head</strong> uses them in rehearsals.</li>
                    <li>If they give offerings: <strong>Finance Head</strong> records their payments against that member.</li>
                </ol>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A student wants to leave a class</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li>The <strong>Student</strong> applies from the student portal (their own enrollment only).</li>
                    <li>The request is <strong>Pending</strong>. People who can approve are notified.</li>
                    <li><strong>Education Head</strong> or <strong>Admin</strong> approves or rejects it.</li>
                    <li>If rejected, the student stays enrolled. The flow stops.</li>
                    <li>If approved, the request is <strong>Education approved</strong>. HR is notified.</li>
                    <li><strong>HR Head</strong> finalizes it. Only then is the enrollment marked Withdrawn.</li>
                </ol>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    Super Admin can do any of these steps. HR cannot skip Education’s approval.
                </p>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Exam marks</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li><strong>Data Encoder</strong> or <strong>Education Head</strong> types the marks and submits the list.</li>
                    <li><strong>Education Head</strong> or <strong>Admin</strong> approves the list.</li>
                    <li>The student can then see <strong>their own</strong> results in the portal.</li>
                </ol>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    The person who typed the marks cannot approve that same list (except Education Head, who may).
                    Another supervisor must sign off.
                </p>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">A spreadsheet of new members</h3>
                <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li>Someone uploads the import file.</li>
                    <li><strong>HR Head</strong>, <strong>Education Head</strong>, or <strong>Admin</strong> must <strong>commit</strong> it.</li>
                    <li>Until it is committed, those people are not real members yet.</li>
                    <li>A Data Encoder cannot commit an import.</li>
                </ol>
            </div>

            <div>
                <h3 class="font-semibold text-base text-gray-900 dark:text-white">Money, property, and aid — who records what</h3>
                <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-2">
                    <li><strong>Tithe or monthly contribution</strong> — Finance Head or Nibret Hisab Head</li>
                    <li><strong>Donation</strong> — Finance, Nibret Hisab, or Charity (when it supports aid)</li>
                    <li><strong>Item in or out of the store</strong> — Inventory Staff or Nibret Hisab Head</li>
                    <li><strong>Aid given to a person</strong> — Charity Head or Revenue &amp; Charity Head</li>
                    <li><strong>Tour passengers and tour attendance</strong> — Tour Head or Revenue &amp; Charity Head</li>
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Who do I call?</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-600 dark:text-gray-400">
                <thead class="text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">I need to…</th>
                        <th class="py-2 font-semibold">Ask</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr><td class="py-2 pr-4">Add or fix a member record</td><td>HR Head</td></tr>
                    <tr><td class="py-2 pr-4">Put someone in a group / handle a contact message</td><td>Internal Relations Head</td></tr>
                    <tr><td class="py-2 pr-4">Enroll a student or change class</td><td>Education Head</td></tr>
                    <tr><td class="py-2 pr-4">Take class attendance</td><td>Education Monitor</td></tr>
                    <tr><td class="py-2 pr-4">Type exam scores</td><td>Data Encoder</td></tr>
                    <tr><td class="py-2 pr-4">Approve exam scores</td><td>Education Head</td></tr>
                    <tr><td class="py-2 pr-4">A student wants to leave</td><td>Student applies → Education Head → HR Head</td></tr>
                    <tr><td class="py-2 pr-4">Record a payment</td><td>Finance Head</td></tr>
                    <tr><td class="py-2 pr-4">Record store items</td><td>Inventory Staff or Nibret Hisab Head</td></tr>
                    <tr><td class="py-2 pr-4">Register a tour passenger</td><td>Tour Head</td></tr>
                    <tr><td class="py-2 pr-4">Register someone for aid</td><td>Charity Head</td></tr>
                    <tr><td class="py-2 pr-4">Publish news or photos</td><td>AV Head</td></tr>
                    <tr><td class="py-2 pr-4">Add a song or rehearsal</td><td>Mezmur Head</td></tr>
                    <tr><td class="py-2 pr-4">Unlock an account or change a role</td><td>Super Admin (Admin can also manage users)</td></tr>
                    <tr><td class="py-2 pr-4">Change church system settings or restore a backup</td><td>Super Admin only</td></tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</div>
