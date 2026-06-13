<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BootstrapAdmin extends Command
{
    protected $signature   = 'a1:bootstrap-admin {email?} {--password=} {--no-interaction}';
    protected $description = 'Create the first super-admin user + workspace. Uses BOOTSTRAP_ADMIN_* env if no flags.';

    public function handle(): int
    {
        $email = $this->argument('email')
            ?: env('BOOTSTRAP_ADMIN_EMAIL', 'admin@a1techflow.com');
        $password = $this->option('password')
            ?: env('BOOTSTRAP_ADMIN_PASSWORD');

        if (User::where('email', $email)->exists()) {
            $this->info("Admin {$email} already exists; skipping.");
            return self::SUCCESS;
        }
        if (! $password) {
            $this->error('No password provided (set BOOTSTRAP_ADMIN_PASSWORD or pass --password=).');
            return self::FAILURE;
        }

        $user = User::create([
            'name'              => 'Platform Admin',
            'email'             => $email,
            'password'          => $password,
            'is_admin'          => true,
            'email_verified_at' => now(),
        ]);

        $plan = Plan::where('slug', 'enterprise')->first();
        $team = Team::create([
            'name'     => 'Platform',
            'slug'     => 'platform-'.Str::lower(Str::random(6)),
            'owner_id' => $user->id,
            'plan_id'  => $plan?->id,
            'trial_ends_at' => null,
        ]);
        $team->users()->attach($user->id, ['role' => 'owner']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->info("Created admin {$email} with workspace '{$team->name}'.");
        return self::SUCCESS;
    }
}
