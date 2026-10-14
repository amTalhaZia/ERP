<?php

namespace App\Enums\Inventory;

enum StorageLocationType: string
{
    case Zone = 'zone';
    case Aisle = 'aisle';
    case Rack = 'rack';
    case Bin = 'bin';
}
