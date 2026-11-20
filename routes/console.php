<?php

use App\Models\User;
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

// Creates an admin account, or makes an existing account an admin. The password is typed in
// (hidden) when the command runs, so it is never stored in the code or in git.
Artisan::command('unibook:make-admin {email} {--name= : Name for a new account} {--password= : Skip the hidden prompt (avoid on shared machines)}', function () {
    $email = strtolower(trim($this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Please give a valid email address.');

        return 1;
    }

    $user = User::where('email', $email)->first();
    $password = $this->option('password');

    if (! $user) {
        $name = $this->option('name') ?: $this->ask('Name', 'Admin');
        $password ??= $this->secret('Password (at least 8 characters, hidden)');
        if ($password === null || $password === '') {
            $this->error('A new account needs a password. If the hidden prompt does not work in this terminal, add --password="..."');

            return 1;
        }
    } elseif ($password === null) {
        $password = $this->secret('New password (leave empty to keep the current one)') ?: null;
    }

    if ($password !== null && strlen($password) < 8) {
        $this->error('The password must be at least 8 characters.');

        return 1;
    }

    $user ??= new User(['name' => $name, 'email' => $email]);
    if ($password !== null) {
        $user->password = $password; // hashed by the model cast
    }
    $user->is_admin = true;
    $user->banned_at = null;
    $user->save();

    $this->info("Admin account {$email} is ready. Log in and open /admin.");

    return 0;
})->purpose('Create an admin account or make an existing user an admin');
