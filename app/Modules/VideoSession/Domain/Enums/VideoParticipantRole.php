<?php

namespace App\Modules\VideoSession\Domain\Enums;

enum VideoParticipantRole: string
{
    case Client = 'client';
    case Professional = 'professional';
}
