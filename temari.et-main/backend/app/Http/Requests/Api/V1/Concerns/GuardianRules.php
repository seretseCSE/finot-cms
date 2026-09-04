<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\GuardianRelationship;
use App\Rules\EthiopianPhone;
use Illuminate\Validation\Rule;

/**
 * One guardian row: either `parent_id` (attach an existing parent found via
 * guardian search — parents are global persons, ADR-011) or a phone + name to
 * provision one. Shared between StoreGuardianRequest (flat) and
 * StoreStudentRequest (nested under guardians.*).
 */
trait GuardianRules
{
    /**
     * @return array<string, mixed>
     */
    public static function guardianRowRules(string $prefix = ''): array
    {
        $parentId = "{$prefix}parent_id";

        return [
            "{$prefix}parent_id" => ['nullable', 'integer', Rule::exists('parents', 'id')->whereNull('deleted_at')],

            // Provisioning path — required when no existing parent is attached.
            // `name` is a legacy single-field alias kept for older clients.
            "{$prefix}name" => ['nullable', 'string', 'max:255'],
            "{$prefix}first_name" => ["required_without_all:{$parentId},{$prefix}name", 'nullable', 'string', 'max:255'],
            "{$prefix}father_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}grandfather_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}phone" => ["required_without:{$parentId}", "prohibits:{$parentId}", 'nullable', 'string', 'max:20', new EthiopianPhone()],
            "{$prefix}email" => ['nullable', 'email', 'max:255'],
            "{$prefix}gender" => ['nullable', Rule::in(['male', 'female'])],
            "{$prefix}secondary_phone" => ['nullable', 'string', 'max:20', new EthiopianPhone()],
            "{$prefix}occupation" => ['nullable', 'string', 'max:255'],
            "{$prefix}employer" => ['nullable', 'string', 'max:255'],
            "{$prefix}country" => ['nullable', 'string', 'max:100'],
            "{$prefix}state" => ['nullable', 'string', 'max:100'],
            "{$prefix}city" => ['nullable', 'string', 'max:100'],
            "{$prefix}sub_city" => ['nullable', 'string', 'max:100'],
            "{$prefix}woreda" => ['nullable', 'string', 'max:100'],
            "{$prefix}house_no" => ['nullable', 'string', 'max:50'],

            // The link itself.
            "{$prefix}relationship" => ['required', Rule::enum(GuardianRelationship::class)],
            "{$prefix}can_view_grades" => ['sometimes', 'boolean'],
            "{$prefix}can_view_attendance" => ['sometimes', 'boolean'],
            "{$prefix}can_pay_fees" => ['sometimes', 'boolean'],
            "{$prefix}can_receive_sms" => ['sometimes', 'boolean'],
            "{$prefix}is_primary" => ['sometimes', 'boolean'],
            "{$prefix}emergency_contact" => ['sometimes', 'boolean'],
            "{$prefix}priority_order" => ['nullable', 'integer', 'min:1', 'max:99'],
            "{$prefix}notes" => ['nullable', 'string', 'max:1000'],
        ];
    }
}
