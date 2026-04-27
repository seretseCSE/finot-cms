<?php

namespace App\Console\Commands;

use App\Models\MemberGroupAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-test-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old test data and fix member_since dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up test data...');

        // Clean up old group assignments that are older than 30 days and ended
        $deletedAssignments = MemberGroupAssignment::where('effective_to', '<', now()->subDays(30))
            ->delete();

        $this->info("Deleted {$deletedAssignments} old group assignments");

        // Update member_since for members who don't have it set
        $updatedMembers = DB::table('members')
            ->whereNull('member_since')
            ->update(['member_since' => now()]);

        $this->info("Updated member_since for {$updatedMembers} members");

        // Clean up any future-dated assignments (invalid data)
        $futureAssignments = MemberGroupAssignment::where('effective_from', '>', now())
            ->delete();

        $this->info("Deleted {$futureAssignments} future-dated assignments");

        $this->info('Test data cleanup completed!');
    }
}
