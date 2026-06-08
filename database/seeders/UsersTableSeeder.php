<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()
            ->create([
               'display_name' => 'Admin',
               'username' => 'admin',
                'password' => password_hash('Asdasd123+++', PASSWORD_DEFAULT),
            ]);
    }
}
