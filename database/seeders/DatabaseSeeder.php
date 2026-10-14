<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeded contractor owners, one company each (the second shows tenant isolation). Clients never log in here;
     * they use the OTP link. With DEMO_LOGINS=true the login page offers these as one-click logins, so never
     * enable that flag on a live server.
     */
    public const LOGINS = [
        ['name' => 'Contractor · Demo Renovation', 'email' => 'test@example.com', 'password' => 'Zx123456', 'company' => 'Demo Renovation Sdn Bhd'],
        ['name' => 'Contractor · Bina Jaya', 'email' => 'bina@example.com', 'password' => 'Zx123456', 'company' => 'Bina Jaya Construction'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::LOGINS as $login) {
            User::factory()
                ->for(Company::factory()->state(['name' => $login['company']]))
                ->create(['name' => $login['name'], 'email' => $login['email'], 'password' => $login['password']]);
        }

        // Run queued work (notifications, thumbnails) inline, so demo data is complete without a worker.
        config(['queue.default' => 'sync']);
        $this->call(DemoJobSeeder::class);
    }
}
