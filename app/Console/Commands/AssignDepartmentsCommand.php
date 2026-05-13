<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Department;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignDepartmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members:assign-departments {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Randomly assign members to departments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Random Department Assignment ===');
        $this->newLine();

        // First, activate all departments
        $this->info('Activating all departments...');
        DB::table('departments')->update(['is_active' => true]);
        $this->info('All departments activated!');
        $this->newLine();

        // Get all active departments
        $departments = Department::where('is_active', true)->get();
        $departmentIds = $departments->pluck('id')->toArray();

        if (empty($departmentIds)) {
            $this->error('No departments found!');
            return 1;
        }

        $this->info('Available Departments:');
        foreach ($departments as $dept) {
            $this->line("- ID: {$dept->id}, Name: {$dept->name_en}");
        }
        $this->newLine();

        // Get member count for confirmation
        $memberCount = Member::count();

        $this->info("Found {$memberCount} members to assign departments");
        $this->newLine();

        if ($memberCount === 0) {
            $this->info('No members found!');
            return 0;
        }

        // Confirm assignment
        if (!$this->option('force')) {
            if (!$this->confirm("Do you want to randomly assign {$memberCount} members to departments?")) {
                $this->info('Assignment cancelled.');
                return 0;
            }
        }

        // Randomly assign departments using chunking to avoid memory issues
        $assignedCount = 0;
        $progressBar = $this->output->createProgressBar($memberCount);
        $progressBar->start();

        Member::chunk(1000, function ($members) use ($departmentIds, &$assignedCount, $progressBar) {
            foreach ($members as $member) {
                $randomDepartmentId = $departmentIds[array_rand($departmentIds)];
                $member->department_id = $randomDepartmentId;
                $member->save();
                $assignedCount++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info('=== Assignment Complete ===');
        $this->info("Total members assigned: {$assignedCount}");
        $this->newLine();

        // Show distribution
        $this->info('Department Distribution:');
        foreach ($departments as $dept) {
            $count = Member::where('department_id', $dept->id)->count();
            $this->line("- {$dept->name_en}: {$count} members");
        }

        $this->newLine();
        $this->info('Done!');

        return 0;
    }
}
