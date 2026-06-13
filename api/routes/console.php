<?php

use App\Console\Commands\BootstrapAdmin;
use App\Console\Commands\CreateAdminUser;
use App\Console\Commands\GatewayHealthCheck;
use App\Console\Commands\PollIncomingSms;
use App\Console\Commands\RotateApiTokens;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', fn () => $this->comment('Send the SMS that matters.'))->purpose('Inspiration');

Schedule::command(GatewayHealthCheck::class)->everyMinute()->withoutOverlapping();
Schedule::command(PollIncomingSms::class)->everyThirtySeconds()->withoutOverlapping();
Schedule::command('a1:billing:record-usage')->dailyAt('02:30');
Schedule::command('a1:billing:trials-expiring')->dailyAt('08:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('telescope:prune --hours=72')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
