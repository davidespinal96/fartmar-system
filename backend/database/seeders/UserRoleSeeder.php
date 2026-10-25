<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador de prueba',
                'email' => 'admin@fartmar.test',
                'role' => 'admin',
            ],
            [
                'name' => 'Auxiliar de farmacia',
                'email' => 'auxiliar@fartmar.test',
                'role' => 'auxiliar_farmacia',
            ],
            [
                'name' => 'Regente de farmacia',
                'email' => 'regente@fartmar.test',
                'role' => 'regente_farmacia',
            ],
            [
                'name' => 'Médico de prueba',
                'email' => 'medico@fartmar.test',
                'role' => 'medico',
            ],
            [
                'name' => 'Auditor de prueba',
                'email' => 'auditor@fartmar.test',
                'role' => 'auditor',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrNew([
                'email' => $userData['email'],
            ]);
            if (! $user->exists) {
                $user->name = $userData['name'];
                $user->role = $userData['role'];
                $user->password = Hash::make('FartmarTest2026!');
                $user->save();
            }
        }
    }
}
