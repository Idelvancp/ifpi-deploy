<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'cti.capau@ifpi.edu.br',
            'password' => bcrypt('ifti.capau.2025'), // Senha fixa
            'student_id' => '2025120adm0001',
        ]);
    }
}
