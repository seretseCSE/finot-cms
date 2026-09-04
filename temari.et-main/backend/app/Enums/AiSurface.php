<?php

namespace App\Enums;

/**
 * The three AI assistant SURFACES the user can talk to — one per access-lane
 * kind, mirroring how the rest of the app splits surfaces (staff workspace /
 * family portal / platform). The user never picks an assistant: the surface
 * is derived from where they open the chat, and the backend composes the
 * agent from every lane they hold there (ADR-010/012). Lanes (AiLane) remain
 * the internal capability unit; the surface is what the UI shows.
 */
enum AiSurface: string
{
    case School = 'school';
    case Family = 'family';
    case Platform = 'platform';
}
