<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Services\PhoneFormattingService;
use App\Services\UserCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserMemberPhoneAutofillTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function find_member_by_phone_matches_stored_and_national_formats(): void
    {
        $member = Member::factory()->create([
            'phone' => '+251911223344',
            'first_name' => 'Abebe',
            'father_name' => 'Kebede',
            'grandfather_name' => 'Tesfaye',
            'email' => 'abebe@example.com',
        ]);

        $service = app(UserCreationService::class);

        $this->assertTrue($member->is($service->findMemberByPhone('+251911223344')));
        $this->assertTrue($member->is($service->findMemberByPhone('911223344')));
        $this->assertNull($service->findMemberByPhone('911000000'));
        $this->assertNull($service->findMemberByPhone(null));
    }

    #[Test]
    public function attributes_from_member_copy_name_and_email(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Abebe',
            'father_name' => 'Kebede',
            'grandfather_name' => 'Tesfaye',
            'email' => 'abebe@example.com',
        ]);

        $attributes = app(UserCreationService::class)->attributesFromMember($member);

        $this->assertSame($member->full_name, $attributes['name']);
        $this->assertSame('abebe@example.com', $attributes['email']);
    }

    #[Test]
    public function attributes_from_member_omit_blank_email(): void
    {
        $member = Member::factory()->create([
            'email' => null,
        ]);

        $attributes = app(UserCreationService::class)->attributesFromMember($member);

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayNotHasKey('email', $attributes);
    }

    #[Test]
    public function normalize_for_auth_matches_dehydrate_for_nine_digit_input(): void
    {
        $this->assertSame(
            PhoneFormattingService::dehydrateStateUsing('911223344'),
            PhoneFormattingService::normalizeForAuth('911223344')
        );
    }
}
