<?php

use App\Models\Entry;
use App\Services\Integrations;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    if (!empty(Entry::getData('settings', 'store')['rate_url'])) {
        app(Integrations::class)->refresh();
    }
})->name('barin-gold-rate')->everyFiveMinutes()->withoutOverlapping();

\Illuminate\Support\Facades\Artisan::command('barin:admin {email}', function () {
    $email = $this->argument('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp((string) config('barin.admin_email'), $email) !== 0) {
        $this->error('Set BARIN_ADMIN_EMAIL first.');
        return 1;
    }
    $password = $this->secret('Password (12 characters minimum)');
    if (strlen((string) $password) < 12) {
        $this->error('Password is too short.');
        return 1;
    }
    \App\Models\User::updateOrCreate(['email' => $email], ['name' => 'مدیر برین‌زر', 'password' => $password]);
    $this->info('Administrator saved.');
});
