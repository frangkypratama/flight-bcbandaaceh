<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeUserSeeder extends Seeder
{
    /**
     * NIP is used, without spaces, as both username and password.
     */
    private const EMPLOYEES = [
        ['Deddy Afdhal', '19820605 200112 1 003'],
        ['Abizar', '19770928 199903 1 002'],
        ['Jefri Adrian', '19740302 200312 1 002'],
        ['Meiriko Sazali Saragih', '19790513 199903 1 002'],
        ['Sukendar', '19850721 200412 1 004'],
        ['Rina Zenvia', '19850918 200412 2 002'],
        ['Tabrani', '19850607 200701 1 002'],
        ['Hafiz Hairullah', '19910112 201310 1 004'],
        ['Faisal Akbar Harahap', '19900808 201210 1 001'],
        ['Andrico Putra Manalu', '19910711 201210 1 001'],
        ['Rasyid Arfi', '19950113 201502 1 002'],
        ['Frangky Pratama Sinurat', '19980807 201801 1 001'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::EMPLOYEES as [$name, $nipSpaced]) {
            $nip = str_replace(' ', '', $nipSpaced);

            User::updateOrCreate(
                ['username' => $nip],
                [
                    'name' => $name,
                    'email' => $nip.'@nip.local',
                    'password' => Hash::make($nip),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
