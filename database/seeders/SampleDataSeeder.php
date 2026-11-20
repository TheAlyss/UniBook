<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * OPTIONAL sample users and listings (all passwords are "password").
     * Run only when you want demo data: php artisan db:seed --class=SampleDataSeeder
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Hafiz Rahman', 'email' => 'hafiz@student.uitm.edu.my'],     // senior seller
            ['name' => 'Nurul Aina', 'email' => 'nurul@student.uitm.edu.my'],       // senior seller
            ['name' => 'Amir Hakimi', 'email' => 'amir@student.uitm.edu.my'],       // senior seller
            ['name' => 'Siti Hajar', 'email' => 'siti@student.uitm.edu.my'],        // senior seller
            ['name' => 'Aina Sofea', 'email' => 'aina@student.uitm.edu.my'],        // senior seller
            ['name' => 'Danial Iskandar', 'email' => 'danial@student.uitm.edu.my'], // junior buyer
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'password' => 'password', 'email_verified_at' => now()]
            );
        }

        $this->call(ListingSeeder::class);
    }
}
