<?php

namespace Tests\Feature;

use App\Filament\Pages\Student\ClassAnnouncements as StudentClassAnnouncementsPage;
use App\Filament\Resources\AnnouncementResource;
use App\Models\ClassAnnouncement;
use App\Models\ClassModel;
use App\Models\InAppNotification;
use App\Models\Member;
use App\Models\MemberParentGuardian;
use App\Models\ParentModel;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Identity\ProvisionParentUser;
use App\Services\Identity\ProvisionStudentUser;
use App\Services\Learning\ClassContentNotifier;
use App\Support\RoleGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentLearningParentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClass(string $name): ClassModel
    {
        return ClassModel::create([
            'name' => $name,
            'program_year' => 1,
            'is_active' => true,
            'created_by' => $this->createEducationHeadUser()->id,
        ]);
    }

    #[Test]
    public function phone_less_kid_does_not_get_student_user_but_parent_does(): void
    {
        ProvisionStudentUser::$enabled = true;
        ProvisionParentUser::$enabled = true;

        $kid = Member::factory()->create([
            'member_type' => 'Kids',
            'phone' => '',
            'first_name' => 'Kid',
        ]);

        $class = $this->makeClass('Grade 1A');

        StudentEnrollment::factory()->enrolled()->create([
            'member_id' => $kid->id,
            'class_id' => $class->id,
        ]);

        $this->assertNull(User::query()->where('member_id', $kid->id)->first());
        $this->assertNull(app(ProvisionStudentUser::class)->sync($kid->fresh()));

        $parent = ParentModel::create([
            'full_name' => 'Parent One',
            'phone' => '+251911111111',
            'is_active' => true,
        ]);

        MemberParentGuardian::create([
            'member_id' => $kid->id,
            'parent_id' => $parent->id,
            'parent_name' => 'Parent One',
            'relationship' => 'Mother',
            'phone' => '+251911111111',
            'is_external' => false,
        ]);

        $user = app(ProvisionParentUser::class)->sync($parent->fresh());

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('parent'));
        $this->assertSame($parent->id, $user->parent_id);
        $this->assertTrue($user->canAccessPanel(\Filament\Panel::make()->id('admin')));
    }

    #[Test]
    public function publishing_class_announcement_notifies_student_and_parent(): void
    {
        $class = $this->makeClass('Grade 2B');

        $enrollment = StudentEnrollment::factory()->enrolled()->create(['class_id' => $class->id]);
        $studentUser = User::query()->where('member_id', $enrollment->member_id)->first();
        $this->assertNotNull($studentUser);

        $parent = ParentModel::create([
            'full_name' => 'Guardian',
            'phone' => '+251922222222',
            'is_active' => true,
        ]);
        MemberParentGuardian::create([
            'member_id' => $enrollment->member_id,
            'parent_id' => $parent->id,
            'parent_name' => 'Guardian',
            'relationship' => 'Father',
            'phone' => '+251922222222',
            'is_external' => false,
        ]);
        $parentUser = app(ProvisionParentUser::class)->sync($parent);

        $announcement = ClassAnnouncement::create([
            'class_id' => $class->id,
            'title' => 'Exam tomorrow',
            'body' => 'Math exam at 9am',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $this->createEducationHeadUser()->id,
        ]);

        app(ClassContentNotifier::class)->announcePublished($announcement);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $studentUser->id,
            'event' => 'class.announcement',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $parentUser->id,
            'event' => 'class.announcement',
        ]);
        $this->assertGreaterThanOrEqual(2, InAppNotification::query()->where('event', 'class.announcement')->count());
    }

    #[Test]
    public function student_cannot_access_cms_announcements(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $user = User::query()->where('member_id', $enrollment->member_id)->first();

        $this->actingAs($user);
        session([RoleGate::SESSION_KEY => 'student']);

        $this->assertFalse($user->can('announcements.view'));
        $this->assertFalse(AnnouncementResource::canViewAny());
        $this->assertTrue(StudentClassAnnouncementsPage::canAccess());
    }

    #[Test]
    public function student_sees_only_own_class_announcements(): void
    {
        $classA = $this->makeClass('Section A');
        $classB = $this->makeClass('Section B');

        $enrollment = StudentEnrollment::factory()->enrolled()->create(['class_id' => $classA->id]);
        $user = User::query()->where('member_id', $enrollment->member_id)->first();

        ClassAnnouncement::create([
            'class_id' => $classA->id,
            'title' => 'For A',
            'body' => 'Hello A',
            'is_published' => true,
            'published_at' => now(),
        ]);
        ClassAnnouncement::create([
            'class_id' => $classB->id,
            'title' => 'For B',
            'body' => 'Hello B',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user);
        session([RoleGate::SESSION_KEY => 'student']);

        $page = app(StudentClassAnnouncementsPage::class);
        $titles = collect($page->items())->pluck('title')->all();

        $this->assertContains('For A', $titles);
        $this->assertNotContains('For B', $titles);
    }
}
