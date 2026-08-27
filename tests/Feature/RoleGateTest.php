<?php

namespace Tests\Feature;

use App\Support\RoleGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_denied(): void
    {
        $this->assertFalse(RoleGate::check());
        $this->assertFalse(RoleGate::is('superadmin'));
        $this->assertFalse(RoleGate::isAny(['admin', 'hr_head']));
        $this->assertFalse(RoleGate::can('members.view'));
        $this->assertNull(RoleGate::user());
    }

    #[Test]
    public function superadmin_is_recognized(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $this->assertTrue(RoleGate::check());
        $this->assertTrue(RoleGate::is('superadmin'));
        $this->assertTrue(RoleGate::isAny(['admin', 'superadmin']));
        $this->assertNotNull(RoleGate::user());
    }

    #[Test]
    public function finance_head_is_not_treated_as_superadmin(): void
    {
        $this->actingAs($this->createFinanceHeadUser());

        $this->assertTrue(RoleGate::check());
        $this->assertTrue(RoleGate::is('finance_head'));
        $this->assertFalse(RoleGate::is('superadmin'));
    }
}
