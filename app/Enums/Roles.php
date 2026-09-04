<?php

namespace App\Enums;

class Roles
{
    public const SUPERADMIN = 'superadmin';
    public const ADMIN = 'admin';
    public const EDUCATION_HEAD = 'education_head';
    public const EDUCATION_MONITOR = 'education_monitor';
    public const DATA_ENCODER = 'data_encoder';
    public const STUDENT = 'student';
    public const PARENT = 'parent';
    public const MEZMUR_HEAD = 'mezmur_head';
    public const WORSHIP_MONITOR = 'worship_monitor';
    public const FINANCE_HEAD = 'finance_head';
    public const NIBRET_HISAB_HEAD = 'nibret_hisab_head';
    public const HR_HEAD = 'hr_head';
    public const INTERNAL_RELATIONS_HEAD = 'internal_relations_head';
    public const CHARITY_HEAD = 'charity_head';
    public const TOUR_HEAD = 'tour_head';
    public const REVENUE_AND_CHARITY_HEAD = 'revenue_and_charity_head';
    public const AV_HEAD = 'av_head';
    public const INVENTORY_STAFF = 'inventory_staff';

    public const STAFF = [
        self::SUPERADMIN,
        self::ADMIN,
        self::EDUCATION_HEAD,
        self::EDUCATION_MONITOR,
        self::DATA_ENCODER,
        self::MEZMUR_HEAD,
        self::WORSHIP_MONITOR,
        self::FINANCE_HEAD,
        self::NIBRET_HISAB_HEAD,
        self::HR_HEAD,
        self::INTERNAL_RELATIONS_HEAD,
        self::CHARITY_HEAD,
        self::TOUR_HEAD,
        self::REVENUE_AND_CHARITY_HEAD,
        self::AV_HEAD,
        self::INVENTORY_STAFF,
    ];

    public const ADMINISTRATORS = [self::SUPERADMIN, self::ADMIN];

    public const ALL_ROLES = [
        self::SUPERADMIN,
        self::ADMIN,
        self::EDUCATION_HEAD,
        self::EDUCATION_MONITOR,
        self::DATA_ENCODER,
        self::STUDENT,
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
        self::AV_HEAD,
        self::INVENTORY_STAFF,
    ];
}
