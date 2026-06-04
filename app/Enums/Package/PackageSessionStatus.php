<?php

namespace App\Enums\Package;

enum PackageSessionStatus: string
{
    case Reserved = 'reserved';
    case Consumed = 'consumed';
    case Released = 'released';
    case Cancelled = 'cancelled';
}
