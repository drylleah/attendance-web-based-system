<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates the default admin account (same credentials as seed.js),
     * and ensures the datetime_config singleton row exists.
     *
     * Safe to run multiple times — uses updateOrCreate / insertOrIgnore.
     */
    public function run(): void
    {
        // ---- Admin user ----
        $existing = DB::table('users')->where('username', 'admin')->first();

        if ($existing) {
            // Update email if it was left blank on an older install
            if (empty($existing->email)) {
                DB::table('users')
                    ->where('username', 'admin')
                    ->update(['email' => 'admin@lorma.edu']);
            }
            $this->command->info('Admin account already exists — skipping creation.');
        } else {
            DB::table('users')->insert([
                'username'   => 'admin',
                'password'   => Hash::make('Att@2024#Xz9!'),
                'role'       => 'admin',
                'email'      => 'admin@lorma.edu',
                'first_name' => null,
                'last_name'  => null,
                'profile_pic'=> null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Admin account created.');
            $this->command->info('  Username : admin');
            $this->command->info('  Password : Att@2024#Xz9!');
            $this->command->warn('  Save this password — it will not be shown again.');
        }

        // ---- Datetime config singleton row ----
        DB::table('datetime_config')->insertOrIgnore([
            'id'   => 1,
            'mode' => 'automatic',
        ]);

        $this->command->info('Datetime config row ready.');
    }
}
