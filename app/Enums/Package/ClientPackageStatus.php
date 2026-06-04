<?php

namespace App\Enums\Package;

enum ClientPackageStatus: string
{
    case Active = 'active';
    case Depleted = 'depleted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
