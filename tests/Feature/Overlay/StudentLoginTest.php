<?php

namespace Tests\Feature\Overlay;

use App\Models\Member;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Identity\ProvisionStudentUser;
use App\Services\PhoneFormattingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function phone_plus_active_enrollment_provisions_student_user(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $member = $enrollment->member;

        $user = User::query()->where('member_id', $member->id)->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertSame($member->phone, $user->phone);
        $this->assertFalse($user->canAccessPanel(\Filament\Panel::make()->id('admin')));
    }

    #[Test]
    public function member_without_enrollment_does_not_get_a_portal_user(): void
    {
        $member = Member::factory()->create();

        $this->assertNull(User::query()->where('member_id', $member->id)->first());
        $this->assertNull(User::query()->where('phone', $member->phone)->where('member_id', $member->id)->first());
        $this->assertNull(app(ProvisionStudentUser::class)->sync($member->fresh()));
    }

    #[Test]
    public function guardian_phone_is_never_used_for_login(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $member = $enrollment->member;
        $user = User::query()->where('member_id', $member->id)->first();

        $this->assertNotSame($member->emergency_contact_phone, $user->phone);
        $this->assertSame($member->phone, $user->phone);
    }

    #[Test]
    public function student_can_login_to_portal_and_not_filament(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $user = User::query()->where('member_id', $enrollment->member_id)->first();
        $user->update(['password' => 'password', 'temp_password_changed' => true]);

        $this->post(route('login.submit'), [
            'phone' => preg_replace('/^\+251/', '', $user->phone),
            'password' => 'password',
        ])->assertRedirect(route('portal.home'));

        $this->actingAs($user)->get(route('portal.home'))->assertOk();
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function new_student_first_password_is_the_national_phone_digits(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $user = User::query()->where('member_id', $enrollment->member_id)->first();
        $national = PhoneFormattingService::nationalDigits($user->phone);

        $this->post(route('login.submit'), [
            'phone' => $national,
            'password' => $national,
        ])->assertRedirect(route('portal.profile'));
    }

    #[Test]
    public function staff_login_on_the_same_page_goes_to_admin(): void
    {
        $staff = $this->createAdminUser();
        $staff->update(['password' => 'password', 'temp_password_changed' => true]);
        $national = PhoneFormattingService::nationalDigits($staff->phone);

        $this->post(route('login.submit'), [
            'phone' => $national,
            'password' => 'password',
        ])->assertRedirect(url('/admin'));
    }

    #[Test]
    public function admin_login_url_sends_guests_to_the_single_login_page(): void
    {
        $this->get('/admin/login')->assertRedirect(route('login'));
    }
}
