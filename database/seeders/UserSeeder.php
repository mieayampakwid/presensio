<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@presensio.test',
                'role' => UserRole::Admin,
            ],
            [
                'username' => 'principal',
                'email' => 'principal@presensio.test',
                'role' => UserRole::Principal,
            ],
            [
                'username' => 'teacher1',
                'email' => 'teacher1@presensio.test',
                'role' => UserRole::Teacher,
            ],
            [
                'username' => 'teacher2',
                'email' => 'teacher2@presensio.test',
                'role' => UserRole::Teacher,
            ],
            [
                'username' => 'staff1',
                'email' => 'staff1@presensio.test',
                'role' => UserRole::Staff,
            ],
            [
                'username' => 'student1',
                'email' => 'student1@presensio.test',
                'role' => UserRole::Student,
            ],
            [
                'username' => 'student2',
                'email' => 'student2@presensio.test',
                'role' => UserRole::Student,
            ],
            [
                'username' => 'parent1',
                'email' => 'parent1@presensio.test',
                'role' => UserRole::Parent,
            ],
            [
                'username' => 'parent2',
                'email' => 'parent2@presensio.test',
                'role' => UserRole::Parent,
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['username' => $data['username']],
                [
                    'email' => $data['email'],
                    'password' => $password,
                    'is_active' => true,
                ]
            );

            if (! $user->roleGrants()->where('role', $data['role']->value)->exists()) {
                $user->roleGrants()->create([
                    'role' => $data['role'],
                    'created_at' => now(),
                ]);
            }
        }
    }
}
