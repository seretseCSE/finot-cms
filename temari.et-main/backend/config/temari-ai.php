<?php

/*
|--------------------------------------------------------------------------
| Temari AI — application-level knobs
|--------------------------------------------------------------------------
|
| THE ONLY PLACE model ids, quotas and AI pricing live. Provider wiring
| (API keys, base URLs) stays in config/ai.php (the Laravel AI SDK config);
| everything Temari-specific is here so a model upgrade or a quota change
| is a config edit, never a code hunt. Never expose these values to the
| frontend except through the entitlement endpoint.
|
*/

return [

    // Primary chat model — every text-only conversational/analytical response.
    'model' => env('TEMARI_AI_MODEL', 'gemini-3.5-flash-lite'),

    // Attachment-capable model — any prompt carrying files (images, PDFs,
    // extracted office text) routes here instead of the primary model,
    // which is cheaper but cannot read file input.
    'attachment_model' => env('TEMARI_AI_ATTACHMENT_MODEL', 'gemini-3.5-flash-lite'),

    // Cheap workhorse — conversation titles, digests, briefings, bulk text.
    'light_model' => env('TEMARI_AI_LIGHT_MODEL', 'gemini-3.1-flash-lite'),

    // Hard cap on agentic tool-call loops per prompt.
    'max_steps' => (int) env('TEMARI_AI_MAX_STEPS', 8),

    // Seconds before a single model call is abandoned.
    'timeout' => (int) env('TEMARI_AI_TIMEOUT', 120),

    /*
    |----------------------------------------------------------------------
    | Plans & quotas (messages per day, per user)
    |----------------------------------------------------------------------
    | free      — B2C teaser for parents/students without a subscription.
    | premium   — the paid parent/student AI upgrade.
    | school    — staff of a school with an active School Plan (ai_plan_until).
    | staff_free— staff teaser when the school has no plan (the upsell hook).
    | platform  — Temari.et staff. 0 = feature off, -1 = unlimited.
    */
    'quotas' => [
        'free' => (int) env('TEMARI_AI_QUOTA_FREE', 200), // 5 -> 200
        'premium' => (int) env('TEMARI_AI_QUOTA_PREMIUM', 200),
        'school' => (int) env('TEMARI_AI_QUOTA_SCHOOL', 200),
        'staff_free' => (int) env('TEMARI_AI_QUOTA_STAFF_FREE', 200), // 5 -> 200
        'platform' => (int) env('TEMARI_AI_QUOTA_PLATFORM', -1),
    ],

    /*
    |----------------------------------------------------------------------
    | B2C subscription (CLAUDE.md §11 — 199 ETB / month)
    |----------------------------------------------------------------------
    */
    'subscription' => [
        'plan' => 'monthly',
        'price_etb' => (float) env('TEMARI_AI_PRICE_ETB', 199),
        'days' => 30,
    ],

    // Max user-message length (characters) accepted by the chat endpoint.
    'max_prompt_length' => (int) env('TEMARI_AI_MAX_PROMPT', 8000),

    // Max image attachments per message (homework photo help).
    'max_attachments' => (int) env('TEMARI_AI_MAX_ATTACHMENTS', 3),
];
