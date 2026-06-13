<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Plan;
use App\Models\Template;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Optional seeder. Run with `php artisan db:seed --class=DemoSeeder` to
 * populate a demo workspace, contacts, a template, and an unsubscribe
 * automation. Useful for screenshots, sales demos, and CI smoke runs.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::where('slug', 'business')->first();

        $user = User::firstOrCreate(
            ['email' => 'demo@a1techflow.com'],
            [
                'name'              => 'Demo User',
                'password'          => Hash::make('demo-pass-1234'),
                'email_verified_at' => now(),
            ],
        );

        $team = Team::firstOrCreate(
            ['slug' => 'demo-workspace'],
            [
                'name'            => 'Demo Workspace',
                'owner_id'        => $user->id,
                'plan_id'         => $plan?->id,
                'trial_ends_at'   => now()->addDays(14),
                'trial_sms_limit' => 50,
                'country'         => 'DK',
                'timezone'        => 'Europe/Copenhagen',
            ],
        );
        $team->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        // 20 demo contacts.
        for ($i = 1; $i <= 20; $i++) {
            Contact::firstOrCreate(
                ['team_id' => $team->id, 'msisdn' => '+4512'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)],
                [
                    'first_name' => 'Demo'.$i,
                    'last_name'  => 'User',
                    'opt_in_status' => 'opted_in',
                ],
            );
        }

        // VIP group.
        $vip = ContactGroup::firstOrCreate(
            ['team_id' => $team->id, 'name' => 'VIP'],
            ['color' => '#2563eb'],
        );
        $vip->contacts()->syncWithoutDetaching(
            Contact::query()->where('team_id', $team->id)->limit(5)->pluck('id')->all(),
        );

        // Template.
        Template::firstOrCreate(
            ['team_id' => $team->id, 'name' => 'Welcome'],
            ['body' => 'Hej {{first_name}} — velkommen til {{team}}!', 'variables' => ['first_name', 'team']],
        );

        // STOP keyword automation.
        Automation::firstOrCreate(
            ['team_id' => $team->id, 'name' => 'STOP unsubscribe'],
            [
                'is_active'      => true,
                'trigger_type'   => 'keyword',
                'trigger_config' => ['keyword' => 'STOP'],
                'actions'        => [['type' => 'send_reply', 'body' => 'Du er afmeldt. Send START for at modtage igen.']],
            ],
        );

        $this->command->info('Demo workspace ready. Login: demo@a1techflow.com / demo-pass-1234');
    }
}
