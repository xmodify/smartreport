<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@bms.local'],
            [
                'name' => 'ผู้ดูแลระบบ (Admin)',
                'password' => Hash::make('admin1234'),
            ]
        );

        $this->call([
            HomepageSeeder::class,
        ]);
    }
}
