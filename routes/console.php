<?php

use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Models\Mirror;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Mirror::each(fn (Mirror $mirror) => SyncMirrorJob::dispatch($mirror));
})->everyFourHours()->name('sync-mirrors');
