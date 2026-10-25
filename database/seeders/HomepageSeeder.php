<?php

namespace Database\Seeders;

use App\Models\Homepage;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Homepage::truncate();

        Homepage::create([
            'hcode' => '10989',
            'image' => 'samples/1.jpg',
            'title' => '2026-10-25 10:00',
            'is_active' => true,
            'order_num' => 1,
            'views_count' => 150,
        ]);

        Homepage::create([
            'hcode' => '10989',
            'image' => 'samples/2.jpg',
            'title' => '2026-10-25 10:30',
            'is_active' => true,
            'order_num' => 2,
            'views_count' => 280,
        ]);

        Homepage::create([
            'hcode' => '10989',
            'image' => 'samples/3.jpg',
            'title' => '2026-10-25 11:00',
            'is_active' => true,
            'order_num' => 3,
            'views_count' => 95,
        ]);
    }
}
