<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create(['name' => '田中太郎', 'email' => 'user1@example.com']);

        User::factory()->create(['name' => '佐藤花子', 'email' => 'user2@example.com']);

        User::factory()->admin()->create(['name' => '管理者', 'email' => 'user3@example.com']);
    }
}
