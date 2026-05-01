<x-filament-panels::page>
    @php
        $tabs = $this->getTabs();
        $userRole = $this->getUserRole();
        $userRoles = $this->getUserDisplayRoles();
        $activeTab = in_array($userRole, array_keys($tabs)) ? $userRole : array_key_first($tabs);
    @endphp

    {{-- Your Roles --}}
    @if(!empty($userRoles))
        <div class="mb-4 px-1">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Your roles:
                @foreach($userRoles as $role)
                    <span class="inline-flex items-center rounded-md bg-primary-50 dark:bg-primary-900 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-300 ring-1 ring-inset ring-primary-600/20 mr-1">
                        {{ $role['label'] }}
                    </span>
                @endforeach
            </p>
        </div>
    @endif

    {{-- Tab navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6" x-data="{ activeTab: '{{ $activeTab }}' }">
        <nav class="-mb-px flex flex-wrap gap-2" aria-label="Tabs">
            @foreach($tabs as $key => $tab)
                <button
                    type="button"
                    x-on:click="activeTab = '{{ $key }}'"
                    :class="activeTab === '{{ $key }}'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                    class="inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition-colors rounded-t-lg"
                >
                    <x-filament::icon icon="{{ $tab['icon'] }}" class="h-4 w-4" />
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>

        {{-- Tab content --}}
        <div class="mt-6 space-y-6">

            {{-- ================================================================ --}}
            {{-- SUPERADMIN TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['superadmin']))
                <div x-show="activeTab === 'superadmin'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Super Admin Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                As a <strong>Super Admin</strong>, you have unrestricted access to every part of the system.
                                This role is typically reserved for system administrators and IT staff who manage the platform
                                itself, not day-to-day church operations.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">User & Role Management</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating Users</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Navigate to <strong>Users</strong> in the sidebar</li>
                                        <li>Click <strong>New User</strong></li>
                                        <li>Fill in name, email, phone, and assign a <strong>department</strong></li>
                                        <li>Choose one or more <strong>roles</strong> (e.g. finance_head, education_head)</li>
                                        <li>The user will receive an email with their temporary password</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Managing Roles</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Roles are defined in the PermissionSeeder. To assign or change roles for an existing user,
                                        go to <strong>Users</strong>, edit the user, and update the Roles field.
                                        A user can have multiple roles (e.g. a single person could be both <em>admin</em> and <em>finance_head</em>).
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Active Sessions</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Click your profile icon (top-right) and select <strong>Manage Active Sessions</strong> to view
                                        and terminate active user sessions across the system.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">System Settings & Configuration</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Global Church Settings</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Navigate to <strong>Global Church Settings</strong> to configure: church name, address,
                                        phone prefix (+251), currency, fiscal year, and organization branding.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Auto-Purge Configuration</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Auto-Purge Settings</strong> to configure automatic cleanup of old records:
                                        audit logs, error logs, soft-deleted records, and temporary data. Set retention periods in days.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Backup & Disaster Recovery</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating Backups</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Backup & Restore</strong></li>
                                        <li>Click <strong>Create Backup</strong> to generate a full database + file backup</li>
                                        <li>Backups are stored on the server and can be downloaded</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Restoring from Backup</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Backup & Restore</strong></li>
                                        <li>Find the backup you want to restore</li>
                                        <li>Click <strong>Restore</strong> and confirm the action</li>
                                        <li><strong class="text-danger-600">Warning:</strong> Restoring replaces current data. All users will be logged out.</li>
                                    </ol>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Monitoring & Diagnostics</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">System Health Dashboard</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <strong>System Health Monitoring</strong> shows CPU, memory, disk usage, and queue status.
                                        Check this regularly to ensure the server is performing well.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Error Log Viewer</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Error Log Viewer</strong> to inspect application errors.
                                        Use this when users report unexpected behavior.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Audit Trail</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <strong>Export Audit Logs</strong> lets you download all activity logs for compliance or review.
                                        Audit logs track who changed what and when.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Tips & Best Practices</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li>Do not use the superadmin account for daily operations -- create an admin account instead</li>
                                <li>Review the audit log monthly for unexpected activity</li>
                                <li>Schedule backups at least weekly</li>
                                <li>Keep the system updated with security patches</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- ADMIN TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['admin']))
                <div x-show="activeTab === 'admin'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Admin Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Admin</strong> role has access to all operational modules but cannot modify
                                system-level settings (backups, system health, church settings, error logs).
                                Admins manage users, members, finance, education, media, charity, and more.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">User Management</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Adding a New User</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Users</strong> in the sidebar</li>
                                        <li>Click <strong>New User</strong></li>
                                        <li>Enter the person's name, email, phone (9 digits after +251), and department</li>
                                        <li>Assign appropriate roles from the dropdown</li>
                                        <li>Save -- the system will send an invitation email</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Managing Sessions</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Manage Active Sessions</strong> (available from your profile menu) to see who is
                                        currently logged in and terminate suspicious sessions.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Navigation Guide</x-slot>
                            <div class="space-y-3">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Key Sidebar Sections</h3>
                                    <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li><strong>Members & Groups</strong> -- Manage church members, groups, and assignments</li>
                                        <li><strong>Finance</strong> -- Contributions, donations, transactions, bank accounts</li>
                                        <li><strong>Reports</strong> -- Donation reports, financial statements, contribution matrix</li>
                                        <li><strong>Education</strong> -- Classes, students, teachers, attendance</li>
                                        <li><strong>Worship</strong> -- Songs, rehearsals, categories</li>
                                        <li><strong>Media & Content</strong> -- Blog posts, announcements, media library, FAQs</li>
                                        <li><strong>Charity</strong> -- Beneficiaries, aid distributions</li>
                                        <li><strong>Tours</strong> -- Tour planning, passengers, attendance</li>
                                        <li><strong>Events</strong> -- Events and registrations</li>
                                    </ul>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Common Admin Tasks</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li><strong>Reset a password</strong> -- Edit the user and set a new password</li>
                                <li><strong>Lock a user</strong> -- Edit the user and toggle their active status</li>
                                <li><strong>View member reports</strong> -- Go to Reports section</li>
                                <li><strong>Manage departments</strong> -- Use the Departments resource</li>
                                <li><strong>Review contact messages</strong> -- Check Contact Messages in the sidebar</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- HR HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['hr_head']))
                <div x-show="activeTab === 'hr_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">HR Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>HR Head</strong> manages church members, member groups, and group assignments
                                across all departments. You have full CRUD access to the Members & Groups module.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Members</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Registering a New Member</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Members</strong> in the sidebar</li>
                                        <li>Click <strong>New Member</strong></li>
                                        <li>Fill in the member's personal information: full name, date of birth, gender, marital status</li>
                                        <li>Enter contact details: phone number (9 digits without +251), email, address</li>
                                        <li>Add emergency contact and spiritual information on the respective tabs</li>
                                        <li>Assign a <strong>department</strong> -- this controls who can see this member</li>
                                        <li>Save the member record</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Updating Member Information</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Search for a member, click to edit, and update any field. Changes are tracked in the audit log.
                                        Members scoped to a department are only visible to users in that department
                                        (except admin/superadmin who see all).
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Exporting Members</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        From the Members list page, use the <strong>Export</strong> button to download member data
                                        as Excel or CSV. You can filter by department, status, or date range before exporting.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Groups & Assignments</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating Groups</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Groups</strong> to create fellowship/service groups.
                                        Assign a group leader, set meeting schedules, and specify the department.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Assigning Members to Groups</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Group Assignments</strong> to add or remove members from groups.
                                        You can assign roles within the group (leader, member, etc.).
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Reports Available</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li><strong>Teacher Attendance Report</strong> -- View teacher attendance across departments (under Attendance section)</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- FINANCE HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['finance_head']))
                <div x-show="activeTab === 'finance_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Finance Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Finance Head</strong> manages all financial operations: contributions,
                                donations, financial transactions, and bank accounts. You have full access to financial
                                reports and can view member/beneficiary data for reporting purposes.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Contributions</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording a Contribution</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Contributions</strong> in the sidebar</li>
                                        <li>Click <strong>New Contribution</strong></li>
                                        <li>Select the member (search by name or ID)</li>
                                        <li>Choose the contribution type and enter the amount</li>
                                        <li>Select the payment method (cash, bank transfer, mobile money)</li>
                                        <li>Set the contribution date</li>
                                        <li>Save -- the contribution is recorded and reflected in reports</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Contribution Amounts</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Contribution Amounts</strong> to define preset amounts for different
                                        contribution types. This speeds up data entry and ensures consistency.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Donations</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording a Donation</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Donations</strong></li>
                                        <li>Click <strong>New Donation</strong></li>
                                        <li>Enter donor information (name, contact)</li>
                                        <li>Select the donation type (General Fund, Building Fund, etc.)</li>
                                        <li>Enter the amount and donation date</li>
                                        <li>Choose payment method</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Donation Reports</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Reports > Donation Report</strong> to filter by date range and donation type.
                                        View totals, trends, and breakdowns by category.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Financial Reports</x-slot>
                            <div class="space-y-3">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    The Reports section in the sidebar contains these key financial reports:
                                </p>
                                <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <li><strong>Contribution Matrix</strong> -- Grid view of all members vs. contribution periods</li>
                                    <li><strong>Contribution Report</strong> -- Detailed listing with filters</li>
                                    <li><strong>Monthly Contribution Reports</strong> -- Monthly summaries</li>
                                    <li><strong>Outstanding Contributions</strong> -- Members with unpaid contributions</li>
                                    <li><strong>Donation Report</strong> -- Donations filtered by date and type</li>
                                    <li><strong>Financial Overview</strong> -- Income vs. expense overview</li>
                                    <li><strong>Financial Statement</strong> -- Detailed financial statement</li>
                                    <li><strong>Financial Audit Trail</strong> -- Every financial change, who did it and when</li>
                                </ul>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Bank Accounts & Transactions</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Manage <strong>Bank Accounts</strong> under Finance. Record <strong>Financial Transactions</strong>
                                for deposits, withdrawals, and transfers. Each transaction is linked to a bank account
                                and tracked in the audit trail.
                            </p>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- NIBRET HISAB HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['nibret_hisab_head']))
                <div x-show="activeTab === 'nibret_hisab_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Nibret Hisab Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Nibret Hisab Head</strong> combines Finance Head permissions with
                                <strong>Inventory Management</strong>. You manage church finances AND physical assets/inventory.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Finance Operations</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                You have the same finance capabilities as the Finance Head. See the
                                <strong>Finance</strong> tab for detailed guides on contributions, donations, reports,
                                bank accounts, and financial transactions.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Inventory Management</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Adding Inventory Items</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Inventory Items</strong></li>
                                        <li>Click <strong>New Item</strong></li>
                                        <li>Enter item name, category, quantity, unit, and unit price</li>
                                        <li>Set the storage location and minimum stock level for alerts</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording Stock Movements</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        When items are received, issued, or transferred, record the movement in
                                        <strong>Inventory Movements</strong> or <strong>Stock Movements</strong>.
                                        Enter the item, quantity, movement type (in/out), date, and reason.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Stock Alerts</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Items that fall below their minimum stock level will appear as <strong>Stock Alerts</strong>.
                                        Review these regularly and restock as needed.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Loss Records</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        If items are damaged, lost, or expired, record it in <strong>Loss Records</strong>.
                                        Include the reason, quantity lost, and date. This keeps inventory counts accurate.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- INVENTORY STAFF TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['inventory_staff']))
                <div x-show="activeTab === 'inventory_staff'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Inventory Staff Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                As <strong>Inventory Staff</strong>, you manage the church's physical assets, supplies,
                                and equipment. Your role covers inventory items, stock movements, loss records, and reports.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Daily Operations</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Adding New Items</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Inventory Items</strong></li>
                                        <li>Click <strong>New Item</strong></li>
                                        <li>Fill in: name, category, quantity, unit, unit price</li>
                                        <li>Set location and minimum stock threshold</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording Movements</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Every time items come in or go out, record a <strong>Stock Movement</strong>:
                                        select the item, enter the quantity, choose the movement type (In/Out/Transfer),
                                        and provide a reason (purchase, issued, donation, etc.).
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Handling Losses</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        If an item is damaged, expired, or lost, create a <strong>Loss Record</strong>.
                                        This automatically adjusts the stock count.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Inventory Reports</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                All Reports in the sidebar are available to you. Key ones for inventory:
                            </p>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                <li><strong>Stock Movement Report</strong> -- History of all stock changes</li>
                                <li><strong>Stock Status Report</strong> -- Current stock levels and alerts</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- EDUCATION TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['education_head']))
                <div x-show="activeTab === 'education_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Education Management Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Education Head</strong> has complete control over the education module:
                                academic years, classes, subjects, students (enrollments), teachers, and attendance.
                                The <strong>Education Monitor</strong> has a subset focused on attendance tracking.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Education Head Guide</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Setting Up Academic Years</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Academic Years</strong></li>
                                        <li>Click <strong>New Academic Year</strong></li>
                                        <li>Set the name (e.g. 2017 Ethiopian Calendar), start date, and end date</li>
                                        <li>Mark as active if this is the current year</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating Classes</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Classes</strong> or <strong>School Classes</strong></li>
                                        <li>Click <strong>New Class</strong></li>
                                        <li>Enter class name, grade level, capacity, and assign an academic year</li>
                                        <li>Assign a teacher if needed</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Enrolling Students</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Student Enrollments</strong></li>
                                        <li>Click <strong>New Enrollment</strong></li>
                                        <li>Select the student (member), class, and academic year</li>
                                        <li>Set enrollment status and date</li>
                                        <li>Use <strong>Bulk Promote</strong> to advance students to the next grade at year-end</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Managing Teachers</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Teachers</strong> to add, edit, or remove teachers. Use
                                        <strong>Teacher Assignments</strong> to assign teachers to specific classes and subjects.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Attendance Management (Both Roles)</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating an Attendance Session</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Create Attendance Session</strong> (under Attendance section)</li>
                                        <li>Select the class and date</li>
                                        <li>Choose session type (morning, afternoon, full-day)</li>
                                        <li>Click Create -- this opens the attendance sheet</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Taking Student Attendance</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Student Attendance</strong></li>
                                        <li>Select the active session or create a new one</li>
                                        <li>For each student, mark: Present, Absent, Late, or Excused</li>
                                        <li>Add notes if needed</li>
                                        <li>Save attendance records</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Taking Teacher Attendance</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Teacher Attendance</strong>, select the session, and mark
                                        attendance for teachers. Same flow as student attendance.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Education Monitor Notes</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        As an Education Monitor, you can:
                                    </p>
                                    <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                        <li>Create and manage attendance sessions</li>
                                        <li>Take student and teacher attendance</li>
                                        <li>Assign substitutes for absent teachers</li>
                                        <li><strong>View only</strong> access to academic years, classes, subjects, and enrollments</li>
                                    </ul>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Education Reports</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li><strong>Attendance Summary Report</strong> -- Overview of attendance by class/period</li>
                                <li><strong>Class Performance Report</strong> -- Student performance across subjects</li>
                                <li><strong>Student Progress Report</strong> -- Individual student progress tracking</li>
                                <li><strong>Teacher Attendance Report</strong> -- Teacher attendance summary</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- WORSHIP TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['worship_monitor']))
                <div x-show="activeTab === 'worship_monitor'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Worship Management Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Worship Monitor</strong> manages songs and rehearsals.
                                The <strong>Mezmur Head</strong> has the same worship permissions plus document management
                                and department viewing capabilities.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Songs</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Adding a New Song</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Songs</strong> in the sidebar</li>
                                        <li>Click <strong>New Song</strong></li>
                                        <li>Enter the song title, lyrics, and original author</li>
                                        <li>Assign a <strong>Category</strong> (e.g. Worship, Thanksgiving, Communion)</li>
                                        <li>Optionally assign a <strong>Subcategory</strong></li>
                                        <li>Set language and key signature</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Managing Categories</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Song Categories</strong> and <strong>Song Subcategories</strong>
                                        to organize songs. Create categories like "Eucharist", "Praise", "Lent",
                                        and subcategories for more specific grouping.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Rehearsals</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Scheduling a Rehearsal</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Rehearsals</strong></li>
                                        <li>Click <strong>New Rehearsal</strong></li>
                                        <li>Set the date, time, and location</li>
                                        <li>Select the choir/group</li>
                                        <li>Add songs to rehearse from your song library</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording Rehearsal Attendance</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Open a rehearsal and use <strong>Rehearsal Attendance</strong> to mark who attended.
                                        This helps track choir member commitment.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- AV HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['av_head']))
                <div x-show="activeTab === 'av_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">AV / Media Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>AV Head</strong> manages all media, content, and communications:
                                media items (photos/videos), blog posts, announcements, FAQs, and documents.
                                You can also schedule content publication.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Media Library</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Uploading Media</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Media Items</strong></li>
                                        <li>Click <strong>New Media Item</strong></li>
                                        <li>Upload the file (image, video, audio)</li>
                                        <li>Add a title and description</li>
                                        <li>Assign a <strong>Category</strong> and <strong>Subcategory</strong></li>
                                        <li>Set visibility (public/private)</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Organizing with Categories</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Use <strong>Media Categories</strong> and <strong>Media Subcategories</strong>
                                        to organize content. Examples: "Sermons", "Events", "Choir", "Sunday Service".
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Content Management</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Blog Posts</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Create and publish blog posts via <strong>Blog Posts</strong>. Add a title,
                                        content (rich text editor), featured image, and tags. You can schedule
                                        posts for future publication.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Announcements</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Post announcements via <strong>Announcements</strong>. Set an expiry date
                                        so old announcements are automatically hidden. You can target specific
                                        departments or make announcements church-wide.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">FAQs</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Manage frequently asked questions via <strong>FAQs</strong>. Organize by
                                        category and set display order. FAQs appear on public-facing pages.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Documents</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Upload and manage documents (PDFs, forms, policies) via <strong>Documents</strong>.
                                        Set access permissions and categorize by type.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- CHARITY HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['charity_head']))
                <div x-show="activeTab === 'charity_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Charity Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Charity Head</strong> manages beneficiaries and aid distributions.
                                You also have access to view contributions, record donations, and view member data
                                for reporting purposes.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Beneficiaries</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Registering a Beneficiary</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Beneficiaries</strong></li>
                                        <li>Click <strong>New Beneficiary</strong></li>
                                        <li>Enter personal details: name, age, gender, address</li>
                                        <li>Select <strong>Type</strong> (Individual, Family, Orphan, Widow, etc.)</li>
                                        <li>Choose <strong>Need Category</strong> (Food, Shelter, Medical, Education, etc.)</li>
                                        <li>Set <strong>Status</strong> (Active, Inactive, Completed)</li>
                                        <li>Add any notes about their situation</li>
                                        <li>Save</li>
                                    </ol>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Aid Distributions</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Recording a Distribution</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Aid Distributions</strong></li>
                                        <li>Click <strong>New Distribution</strong></li>
                                        <li>Select the beneficiary</li>
                                        <li>Choose <strong>Aid Type</strong> (Monetary, Food, Clothing, Medical, etc.)</li>
                                        <li>If monetary, enter the amount in ETB</li>
                                        <li>If non-monetary, describe the items and quantity</li>
                                        <li>Set the distribution date</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Locking Distributions</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        After a distribution is verified, you can <strong>lock</strong> it to prevent
                                        accidental edits. Only a Charity Head can unlock it.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Charity Reports</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li><strong>Charity Report</strong> -- Full overview with filters by date and aid type</li>
                                <li><strong>Beneficiary Report</strong> -- Beneficiary status, type, and need analysis</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- TOUR HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['tour_head']))
                <div x-show="activeTab === 'tour_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Tour Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Tour Head</strong> manages tour planning, passenger registration,
                                and tour attendance. You can view member data for passenger selection.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Managing Tours</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Creating a Tour</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Go to <strong>Tours</strong></li>
                                        <li>Click <strong>New Tour</strong></li>
                                        <li>Enter tour name, destination, and description</li>
                                        <li>Set start date, end date, and departure time</li>
                                        <li>Enter cost per passenger (if applicable)</li>
                                        <li>Set maximum number of passengers</li>
                                        <li>Save</li>
                                    </ol>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Adding Passengers</h3>
                                    <ol class="list-decimal ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li>Open the tour and go to <strong>Passengers</strong></li>
                                        <li>Click <strong>Add Passenger</strong></li>
                                        <li>Select a member from the list</li>
                                        <li>Optionally record payment status</li>
                                        <li>Save</li>
                                    </ol>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Tour Attendance</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Use <strong>Tour Attendance</strong> to mark which registered passengers
                                actually attended the tour. Create an attendance session for the tour date
                                and check off attendees.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Tour Reports</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Go to <strong>Reports > Tour Report</strong> for an overview of all tours,
                                passenger counts, attendance rates, and revenue (if applicable).
                            </p>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- INTERNAL RELATIONS HEAD TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['internal_relations_head']))
                <div x-show="activeTab === 'internal_relations_head'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Internal Relations Head Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                The <strong>Internal Relations Head</strong> manages member relations, documents,
                                and can delete media items when needed. You have full member management
                                (same as HR Head) plus document management, department viewing, and
                                contact message viewing.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Member Management</x-slot>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                You have the same member, group, and group assignment capabilities as the HR Head.
                                See the <strong>HR Head</strong> tab for detailed guides on:
                            </p>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                <li>Registering new members</li>
                                <li>Updating member information</li>
                                <li>Managing groups and assignments</li>
                                <li>Exporting member data</li>
                            </ul>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Documents & Communications</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Document Management</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Go to <strong>Documents</strong> to upload, organize, and manage church documents.
                                        You have full create, read, update, and delete access.
                                    </p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Contact Messages</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        View messages sent through the church's contact form (view-only access).
                                        These can be found in <strong>Contact Messages</strong>.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                    </div>
                </div>
            @endif

            {{-- ================================================================ --}}
            {{-- DEPARTMENT SECRETARY / STAFF TAB --}}
            {{-- ================================================================ --}}
            @if(isset($tabs['department_secretary']))
                <div x-show="activeTab === 'department_secretary'" x-cloak>
                    <div class="space-y-6">

                        <x-filament::section>
                            <x-slot name="heading">Department Secretary & Staff Overview</x-slot>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                <strong>Department Secretaries</strong> can create, view, and update department-scoped
                                resources but cannot delete. <strong>Staff</strong> members have read-only access
                                to department resources. Both roles are scoped to their assigned department.
                            </p>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Department Secretary Guide</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">What You Can Do</h3>
                                    <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li><strong>View, Create, Update</strong> department resources</li>
                                        <li><strong>View, Create, Update</strong> members in your department</li>
                                        <li><strong>View, Create, Update</strong> events</li>
                                        <li><strong>View, Create, Update</strong> contributions</li>
                                        <li><strong>View, Create, Update</strong> inventory items</li>
                                        <li><strong>Upload and search</strong> documents</li>
                                        <li><strong>Cannot delete</strong> any resource</li>
                                    </ul>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Department Scoping</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        You will only see records that belong to your assigned department.
                                        For example, if you are in the "Sunday School" department, you will only see
                                        members, events, and resources linked to Sunday School.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Staff Guide (Read-Only)</x-slot>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">What You Can Do</h3>
                                    <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1 mt-1">
                                        <li><strong>View only</strong> access to department resources</li>
                                        <li>View members in your department</li>
                                        <li>View events and contributions</li>
                                        <li>View inventory items and beneficiaries</li>
                                        <li>View and download documents</li>
                                        <li>Access reports (view only)</li>
                                    </ul>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-base text-gray-900 dark:text-white">Requesting Changes</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        If you need to update or create a record, contact your <strong>Department Secretary</strong>,
                                        <strong>HR Head</strong>, or <strong>Admin</strong>. They have the necessary permissions
                                        to make changes on your behalf.
                                    </p>
                                </div>
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Common Tasks for Both Roles</x-slot>
                            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                <li><strong>Update your profile</strong> -- Click your name (top-right) > Edit Profile</li>
                                <li><strong>Search members</strong> -- Use the Members list with filters</li>
                                <li><strong>View reports</strong> -- Access Reports in the sidebar (read-only)</li>
                                <li><strong>Download documents</strong> -- Open Documents and click download</li>
                            </ul>
                        </x-filament::section>

                    </div>
                </div>
            @endif

        </div>
    </div>

</x-filament-panels::page>
