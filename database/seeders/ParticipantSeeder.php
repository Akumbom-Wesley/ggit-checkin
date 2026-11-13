<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class ParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/registrations.json')), true);

        foreach ($rows as $row) {
            // Safe to re-run: matches on name + email, never duplicates.
            Participant::updateOrCreate(
                ['full_name' => $row['full_name'], 'email' => $row['email']],
                [
                    'phone'         => $row['phone'],
                    'notes'         => $row['notes'],
                    'payment_proof' => $row['payment_proof'],
                    'registered_at' => $row['registered_at'],
                    'category'      => 'registered',
                    'paid'          => true, // includes the bulk-paid group
                ]
            );
        }

        $this->command->info(count($rows).' registrations imported. Total in DB: '.Participant::count());
    }
}