<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            PlanSeeder::class,
        ]);

        $this->createAdmin();

        // A throwaway member account, for local development only. Skipped in
        // production so `db:seed` on a live box cannot create a known login.
        if (app()->environment('local')) {
            $this->createDemoMember();
        }
    }

    private function createAdmin(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@zenvora.test']);

        if ($admin->exists) {
            // Do not silently reset the password of an existing admin.
            $admin->grantAdmin();
            $this->command->warn('Admin admin@zenvora.test already exists -- password left unchanged.');
        } else {
            // is_admin and email_verified_at are not fillable (see the User
            // model), so forceFill is required rather than optional here.
            $admin->forceFill([
                'name' => 'Zenvora Admin',
                'phone' => '+2348000000001',
                'password' => 'password',
                'referral_code' => User::generateReferralCode(),
                'is_admin' => true,
                'email_verified_at' => now(),
            ])->save();

            $admin->wallet()->create();

            $this->command->info('Admin created: admin@zenvora.test / password');
            $this->command->warn('Change this password before deploying anywhere public.');
        }
    }

    private function createDemoMember(): void
    {
        if (User::query()->where('email', 'member@zenvora.test')->exists()) {
            return;
        }

        $member = User::query()->create([
            'name' => 'Ada Member',
            'email' => 'member@zenvora.test',
            'phone' => '+2348000000002',
            'password' => 'password',
            'referral_code' => User::generateReferralCode(),
        ]);

        $member->markEmailAsVerified();
        $member->wallet()->create();

        $this->command->info('Demo member created: member@zenvora.test / password');
    }
}
