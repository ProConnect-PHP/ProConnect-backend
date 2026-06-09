<?php

namespace App\Enums\Video;

enum VideoSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
