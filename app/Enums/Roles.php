<?php

namespace App\Enums;

class Roles
{
    // Core administrative roles
    public const SUPERADMIN = 'superadmin';
    public const ADMIN = 'admin';

    // Education roles
    public const EDUCATION_HEAD = 'education_head';
    public const EDUCATION_MONITOR = 'education_monitor';
    public const TEACHER = 'teacher';

    // Member roles
    public const MEMBER = 'member';
    public const PARENT = 'parent';

    // Worship roles
    public const MEZMUR_HEAD = 'mezmur_head';
    public const WORSHIP_MONITOR = 'worship_monitor';

    // Finance roles
    public const FINANCE_HEAD = 'finance_head';
    public const NIBRET_HISAB_HEAD = 'nibret_hisab_head';

    // HR roles
    public const HR_HEAD = 'hr_head';
    public const INTERNAL_RELATIONS_HEAD = 'internal_relations_head';

    // Charity roles
    public const CHARITY_HEAD = 'charity_head';

    // Tour roles
    public const TOUR_HEAD = 'tour_head';
    public const TOUR_MANAGER = 'tour_manager';

    // Revenue & Charity
    public const REVENUE_AND_CHARITY_HEAD = 'revenue_and_charity_head';

    // Department roles
    public const SECRETARY = 'secretary';
    public const AV_HEAD = 'av_head';

    // Common role combinations
    public const ADMINISTRATORS = [
        self::SUPERADMIN,
        self::ADMIN,
    ];

    public const EDUCATION_MANAGERS = [
        self::EDUCATION_HEAD,
        self::EDUCATION_MONITOR,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const DEPARTMENT_MANAGERS = [
        self::EDUCATION_HEAD,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const EVENT_MANAGERS = [
        self::SUPERADMIN,
        self::ADMIN,
        self::AV_HEAD,
    ];

    public const WORSHIP_MANAGERS = [
        self::MEZMUR_HEAD,
        self::WORSHIP_MONITOR,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const FINANCE_MANAGERS = [
        self::FINANCE_HEAD,
        self::NIBRET_HISAB_HEAD,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const HR_MANAGERS = [
        self::HR_HEAD,
        self::INTERNAL_RELATIONS_HEAD,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const CHARITY_MANAGERS = [
        self::CHARITY_HEAD,
        self::REVENUE_AND_CHARITY_HEAD,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const TOUR_MANAGERS = [
        self::TOUR_HEAD,
        self::TOUR_MANAGER,
        self::REVENUE_AND_CHARITY_HEAD,
        self::ADMIN,
        self::SUPERADMIN,
    ];

    public const ALL_ROLES = [
        self::SUPERADMIN,
        self::ADMIN,
        self::EDUCATION_HEAD,
        self::EDUCATION_MONITOR,
        self::TEACHER,
        self::MEMBER,
        self::PARENT,
        self::MEZMUR_HEAD,
        self::WORSHIP_MONITOR,
        self::FINANCE_HEAD,
        self::NIBRET_HISAB_HEAD,
        self::HR_HEAD,
        self::INTERNAL_RELATIONS_HEAD,
        self::CHARITY_HEAD,
        self::REVENUE_AND_CHARITY_HEAD,
        self::TOUR_HEAD,
        self::TOUR_MANAGER,
        self::SECRETARY,
        self::AV_HEAD,
    ];
}
