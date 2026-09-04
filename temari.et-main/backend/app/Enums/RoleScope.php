<?php

namespace App\Enums;

enum RoleScope: string
{
    case Platform = 'platform';
    case School = 'school';
    case Branch = 'branch';

    /**
     * Relationship roles (student, parent, tutor, vendor) are NOT granted via
     * memberships — access is derived from relationships (own enrollment,
     * student_guardians link, own tutoring engagement) and served through the
     * /me endpoints. They can never be assigned to a school or branch.
     */
    case Relationship = 'relationship';
}
