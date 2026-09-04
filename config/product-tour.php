<?php

return [

    'enabled' => env('PRODUCT_TOUR_ENABLED', true),

    'excluded_environments' => ['testing', 'local'],

    'panels' => ['admin'],

    'supported_roles' => [
        'superadmin',
        'admin',
        'finance',
        'hr',
        'registrar',
        'teacher',
        'parent',
        'data_encoder',
        'student',
        'education_head',
        'education_monitor',
    ],

    'current_version' => env('PRODUCT_TOUR_VERSION', '1.1.0'),

    'auto_start' => [
        'enabled' => true,
        'first_tour' => 'onboarding',
        'on_version_change' => true,
        'delay_ms' => 800,
    ],

    'retry' => [
        'max_attempts' => 3,
        'selector_timeout_ms' => 5000,
        'retry_interval_ms' => 500,
    ],

    'animation' => [
        'enabled' => true,
        'duration_ms' => 300,
        'easing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
    ],

    'accessibility' => [
        'keyboard_navigation' => true,
        'focus_trap' => true,
        'screen_reader_support' => true,
        'reduced_motion' => true,
        'high_contrast' => false,
    ],

    'dark_mode' => [
        'enabled' => true,
        'auto_detect' => true,
        'tooltip_class' => 'driver-dark',
    ],

    'mobile' => [
        'enabled' => true,
        'fullscreen_tooltips' => true,
        'side_padding' => 12,
        'top_offset' => 60,
    ],

    'feature_flags' => [
        'onboarding_tour' => true,
        'feature_discovery' => true,
        'changelog_tours' => true,
        'contextual_hints' => true,
    ],

    'excluded_routes' => [
        'admin.password.change',
        'admin.edit-profile',
    ],

    'selector_strategy' => [
        'prefer_data_attributes' => true,
        'fallback_to_css' => true,
        'use_mutation_observer' => true,
    ],

    'analytics' => [
        'enabled' => true,
        'track_all_events' => true,
        'sample_rate' => 100,
        'events' => [
            'started', 'completed', 'skipped', 'abandoned',
            'resumed', 'restarted', 'failed', 'timeout',
            'step_changed', 'hint_dismissed', 'hint_clicked',
        ],
    ],

    'timeout' => [
        'idle_minutes' => 30,
        'step_timeout_ms' => 30000,
        'session_timeout_minutes' => 60,
    ],

    'tours' => [
        'onboarding' => [
            'label' => 'Welcome to FINOTE',
            'description' => 'Get started with your FINOTE administrative panel',
            'version' => '1.1.0',
            'roles' => ['superadmin', 'admin', 'finance', 'hr', 'registrar', 'teacher', 'parent', 'data_encoder', 'student', 'education_head', 'education_monitor'],
            'pages' => ['dashboard'],
            'auto_start' => true,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => true,
        ],
        'dashboard_overview' => [
            'label' => 'Dashboard Overview',
            'description' => 'Learn about your dashboard widgets and key metrics',
            'version' => '1.1.0',
            'roles' => ['superadmin', 'admin', 'finance', 'hr', 'registrar', 'teacher', 'parent', 'data_encoder', 'student', 'education_head', 'education_monitor'],
            'pages' => ['dashboard'],
            'auto_start' => false,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => true,
        ],
        'members_management' => [
            'label' => 'Members Management',
            'description' => 'Manage church members and their information',
            'version' => '1.0.0',
            'roles' => ['superadmin', 'admin', 'registrar'],
            'pages' => ['members'],
            'auto_start' => false,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => false,
        ],
        'finance_overview' => [
            'label' => 'Financial Overview',
            'description' => 'Track donations, contributions, and financial reports',
            'version' => '1.0.0',
            'roles' => ['superadmin', 'admin', 'finance'],
            'pages' => ['finance'],
            'auto_start' => false,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => false,
        ],
        'attendance_tracking' => [
            'label' => 'Attendance Tracking',
            'description' => 'Track attendance for classes and services',
            'version' => '1.0.0',
            'roles' => ['superadmin', 'admin', 'teacher', 'registrar'],
            'pages' => ['attendance'],
            'auto_start' => false,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => false,
        ],
        'donations' => [
            'label' => 'Donations Management',
            'description' => 'Manage and track donations',
            'version' => '1.0.0',
            'roles' => ['superadmin', 'admin', 'finance'],
            'pages' => ['donations'],
            'auto_start' => false,
            'show_progress' => true,
            'allow_skip' => true,
            'allow_resume' => false,
        ],
    ],

    'feature_discovery' => [
        'enabled' => true,
        'max_hints_per_session' => 3,
        'dismiss_for_days' => 7,
        'hint_delay_ms' => 2000,
    ],
];
