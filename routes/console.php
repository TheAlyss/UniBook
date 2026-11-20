<?php

use App\Support\Covers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes scanned photos that were never posted, and seller photos no listing or book uses any more.
Artisan::command('covers:prune {--hours=' . Covers::TEMP_HOURS . ' : Only delete files older than this}', function () {
    $deleted = Covers::prune((int) $this->option('hours'));
    $this->info("Deleted {$deleted['scans']} unused scan(s) and {$deleted['orphans']} unused cover photo(s).");
})->purpose('Delete unused scanned photos and cover photos');

Schedule::command('covers:prune')->daily();
