<?php

namespace Tests\Feature\Overlay;

use App\Filament\Resources\MemberImportResource;
use App\Filament\Resources\MemberResource;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OverlayRbacTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function member_role_is_not_seeded(): void
    {
        $this->assertNull(Role::query()->where('name', 'member')->first());
    }

    #[Test]
    public function all_remaining_roles_have_profile_update(): void
    {
        foreach (\App\Enums\Roles::ALL_ROLES as $role) {
            $user = User::factory()->withRole($role)->create();
            $this->assertTrue($user->can('profile.update'), $role.' should have profile.update');
        }
    }

    #[Test]
    public function encoder_cannot_import_or_crud_members(): void
    {
        $encoder = User::factory()->dataEncoder()->create();

        $this->assertTrue($encoder->can('results.record'));
        $this->assertTrue($encoder->can('classes.view'));
        $this->assertTrue($encoder->can('subjects.view'));
        $this->assertTrue($encoder->can('students.view'));
        $this->assertFalse($encoder->can('imports.commit'));
        $this->assertFalse($encoder->can('members.create'));
        $this->assertFalse($encoder->can('members.update'));
        $this->assertFalse($encoder->can('members.delete'));

        $this->actingAs($encoder);
        $this->assertFalse(MemberImportResource::canViewAny());
        $this->assertFalse(MemberResource::canCreate());
        $this->assertTrue($encoder->canAccessPanel(Panel::make()->id('admin')));
    }

    #[Test]
    public function student_only_cannot_access_filament(): void
    {
        $student = User::factory()->student()->create();

        $this->assertFalse($student->canAccessPanel(Panel::make()->id('admin')));
        $this->actingAs($student)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function multi_role_head_and_student_can_open_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['education_head', 'student']);

        $this->assertTrue($user->canAccessPanel(Panel::make()->id('admin')));
        $this->assertTrue($user->can('withdrawal.approve'));
        $this->assertTrue($user->can('imports.commit'));
        $this->assertTrue($user->can('results.approve'));
    }
}
