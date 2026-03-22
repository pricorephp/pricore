<?php

namespace App\Domains\Mirror\Contracts\Enums;

enum SyncVersionResult: string
{
    case Added = 'added';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
    case DistFailed = 'dist_failed';
}
