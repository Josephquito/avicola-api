<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Erik Quito',
            'email' => '154erik@gmail.com',
            'password' => Hash::make('220901'),
            'role' => 'admin',
            'es_socio' => true,
        ]);
    }
}