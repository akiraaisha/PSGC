<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        User::create([
            'name' => 'Julian Lugod',
            'email' => 'julianlugod@protonmail.com',
            'password' => Hash::make('Clashroom@123'), // Ensure to hash the password
            // Add other fields as needed
        ]);
        $this->call([
//            CityMunicipalitySeeder::class
//            ImportCsvSeederPSGC::class,
            PSGCImporter_v1::class,
        ]);


    }
}
