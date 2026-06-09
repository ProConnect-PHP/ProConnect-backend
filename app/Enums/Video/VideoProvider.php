<?php

namespace App\Enums\Video;

enum VideoProvider: string
{
    case Simulator = 'simulator';
    case LiveKit = 'livekit';
    case ExternalUrl = 'external_url';
}
