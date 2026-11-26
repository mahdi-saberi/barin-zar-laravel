<?php

use App\Services\RateUpdater;
use App\Services\OrderPayments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::useCache('database');
Schedule::call(fn () => app(RateUpdater::class)->run('scheduled'))
    ->name('barin-gold-rate')->everyMinute()->onOneServer()->withoutOverlapping(2);
Schedule::call(fn () => app(OrderPayments::class)->expireOrders())
    ->name('barin-expire-orders')->everyMinute()->onOneServer()->withoutOverlapping(2);

Artisan::command('barin:rate', function (RateUpdater $updater) {
    $status = $updater->run('manual');
    $this->line($status['attempted_at'].' '.$status['message']);

    return $status['state'] === 'updated' ? 0 : 1;
});

Artisan::command('barin:admin {email}', function () {
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
