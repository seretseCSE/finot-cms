<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Enums\Roles;

class EventPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:manage-events {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage event permissions - only assign to superadmin, admin, and audiovisual roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Event Permissions Management ===');
        $this->newLine();

        // Get all roles
        $roles = DB::table('roles')->get();
        $this->info('Available Roles:');
        foreach ($roles as $role) {
            $this->line("- ID: {$role->id}, Name: {$role->name}");
        }
        $this->newLine();

        // Get all permissions
        $permissions = DB::table('permissions')->get();
        $this->info("Total Permissions: " . $permissions->count());
        $this->newLine();

        // Find event-related permissions
        $eventPermissions = [];
        foreach ($permissions as $permission) {
            if (stripos($permission->name, 'event') !== false) {
                $eventPermissions[] = $permission;
            }
        }

        $this->info("Event-Related Permissions (" . count($eventPermissions) . "):");
        foreach ($eventPermissions as $perm) {
            $this->line("- ID: {$perm->id}, Name: {$perm->name}");
        }
        $this->newLine();

        // Get target roles (superadmin, admin, av_head as audiovisual)
        $targetRoles = DB::table('roles')->whereIn('name', Roles::EVENT_MANAGERS)->get();
        $this->info('Target Roles:');
        foreach ($targetRoles as $role) {
            $this->line("- {$role->name} (ID: {$role->id})");
        }
        $this->newLine();

        if (empty($eventPermissions)) {
            $this->error('No event-related permissions found!');
            return 1;
        }

        // Confirm action
        if (!$this->option('force')) {
            if (!$this->confirm("Do you want to remove event permissions from all roles and assign them only to superadmin, admin, and av_head (audiovisual) roles?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Clear existing event permissions from all roles first
        $this->info('Clearing existing event permissions from all roles...');
        DB::table('role_has_permissions')
            ->whereIn('permission_id', array_column($eventPermissions, 'id'))
            ->delete();
        $this->info('Cleared all event permissions.');
        $this->newLine();

        // Assign event permissions only to target roles
        foreach ($targetRoles as $role) {
            $this->info("Assigning event permissions to {$role->name}...");

            $assignments = [];
            foreach ($eventPermissions as $perm) {
                $assignments[] = [
                    'role_id' => $role->id,
                    'permission_id' => $perm->id,
                ];
            }

            if (!empty($assignments)) {
                DB::table('role_has_permissions')->insert($assignments);
                $this->line("  - Assigned " . count($assignments) . " permissions");
            }
        }

        $this->newLine();
        $this->info('=== Assignment Complete ===');

        // Verify assignments
        $this->newLine();
        $this->info('Final Assignment Summary:');
        foreach ($targetRoles as $role) {
            $count = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->where('role_has_permissions.role_id', $role->id)
                ->where('permissions.name', 'like', '%event%')
                ->count();

            $this->line("- {$role->name}: {$count} event permissions");
        }

        $this->newLine();
        $this->info('Event permissions are now only available to: superadmin, admin, and av_head (audiovisual) roles!');
        $this->newLine();
        $this->info('Done!');

        return 0;
    }
}
