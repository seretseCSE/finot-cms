<?php

namespace Database\Seeders;

use App\Models\PredefinedReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class PredefinedReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->role('superadmin')->first()
            ?? User::query()->role('admin')->first()
            ?? User::query()->first();

        $reports = [
            [
                'name' => 'Active Members',
                'slug' => 'active-members',
                'description' => 'List of all active church members',
                'resource_type' => 'members',
                'filter_criteria' => ['status' => 'Active'],
                'columns' => ['member_code', 'first_name', 'father_name', 'phone', 'gender', 'member_type', 'department_id'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'Paid Contributions (Current Year)',
                'slug' => 'paid-contributions-current-year',
                'description' => 'All paid contributions for the current academic year',
                'resource_type' => 'contributions',
                'filter_criteria' => ['is_paid' => true],
                'columns' => ['member_id', 'academic_year_id', 'month', 'amount', 'payment_date', 'payment_method'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Outstanding Contributions',
                'slug' => 'outstanding-contributions',
                'description' => 'Members with unpaid contributions',
                'resource_type' => 'contributions',
                'filter_criteria' => ['is_paid' => false],
                'columns' => ['member_id', 'academic_year_id', 'month', 'amount', 'status'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'Monthly Donations',
                'slug' => 'monthly-donations',
                'description' => 'Donations received this month',
                'resource_type' => 'donations',
                'filter_criteria' => [],
                'columns' => ['donor_name', 'amount', 'donation_date', 'donation_type', 'recorded_by'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 4,
            ],
            [
                'name' => 'Financial Transactions',
                'slug' => 'financial-transactions',
                'description' => 'All income and expense transactions',
                'resource_type' => 'financial_transactions',
                'filter_criteria' => [],
                'columns' => ['transaction_id', 'type', 'title', 'amount', 'category', 'transaction_date', 'payment_method'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 5,
            ],
            [
                'name' => 'Attendance Summary',
                'slug' => 'attendance-summary',
                'description' => 'General attendance records',
                'resource_type' => 'attendance',
                'filter_criteria' => [],
                'columns' => ['member_id', 'event_type', 'event_date', 'status'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 6,
            ],
            [
                'name' => 'Active Beneficiaries',
                'slug' => 'active-beneficiaries',
                'description' => 'All active charity beneficiaries',
                'resource_type' => 'beneficiaries',
                'filter_criteria' => ['status' => 'Active'],
                'columns' => ['beneficiary_code', 'full_name', 'phone', 'type', 'need_category', 'dependents_count'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 7,
            ],
            [
                'name' => 'Aid Distributions',
                'slug' => 'aid-distributions',
                'description' => 'All aid distribution records',
                'resource_type' => 'aid_distributions',
                'filter_criteria' => [],
                'columns' => ['beneficiary_id', 'distribution_date', 'aid_type', 'amount', 'distributed_by'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 8,
            ],
            [
                'name' => 'Tour Passengers',
                'slug' => 'tour-passengers',
                'description' => 'Confirmed passengers for all tours',
                'resource_type' => 'tour_passengers',
                'filter_criteria' => ['status' => 'Confirmed'],
                'columns' => ['passenger_code', 'tour_id', 'full_name', 'phone', 'passenger_count', 'registration_date'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 9,
            ],
            [
                'name' => 'Upcoming Rehearsals',
                'slug' => 'upcoming-rehearsals',
                'description' => 'Scheduled rehearsals with attendance',
                'resource_type' => 'rehearsals',
                'filter_criteria' => ['status' => 'Scheduled'],
                'columns' => ['date_time', 'location', 'status', 'recurrence_type'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 10,
            ],
            [
                'name' => 'Teacher Assignments',
                'slug' => 'teacher-assignments',
                'description' => 'Active teacher class assignments',
                'resource_type' => 'teachers',
                'filter_criteria' => ['status' => 'Active'],
                'columns' => ['teacher_code', 'full_name', 'phone', 'qualifications', 'status'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 11,
            ],
            [
                'name' => 'Student Enrollments',
                'slug' => 'student-enrollments',
                'description' => 'Current student enrollments by class',
                'resource_type' => 'student_enrollments',
                'filter_criteria' => ['status' => 'Enrolled'],
                'columns' => ['member_id', 'class_id', 'academic_year_id', 'enrolled_date', 'status'],
                'format' => 'excel',
                'is_active' => true,
                'display_order' => 12,
            ],
            [
                'name' => 'Low Stock Inventory',
                'slug' => 'low-stock-inventory',
                'description' => 'Inventory items with low stock levels',
                'resource_type' => 'inventory_items',
                'filter_criteria' => [],
                'columns' => ['item_code', 'name', 'category', 'quantity', 'unit', 'location', 'status'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 13,
            ],
            [
                'name' => 'Upcoming Events',
                'slug' => 'upcoming-events',
                'description' => 'Published upcoming events',
                'resource_type' => 'events',
                'filter_criteria' => ['status' => 'Published'],
                'columns' => ['name', 'date_time', 'location', 'registration_required', 'max_capacity', 'status'],
                'format' => 'screen',
                'is_active' => true,
                'display_order' => 14,
            ],
        ];

        foreach ($reports as $report) {
            PredefinedReport::updateOrCreate(
                ['slug' => $report['slug']],
                array_merge($report, ['created_by' => $admin?->id])
            );
        }
    }
}
