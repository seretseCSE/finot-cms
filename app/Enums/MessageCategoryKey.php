<?php

namespace App\Enums;

enum MessageCategoryKey: string
{
    case Announcement = 'announcement';
    case Reminder = 'reminder';
    case EventInvite = 'event_invite';
    case Emergency = 'emergency';
}
