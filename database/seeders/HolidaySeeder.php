<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Philippine regular and special non-working holidays. Holidays with moving dates (Eid'l Fitr,
 * Eid'l Adha, additional special days) are proclaimed yearly; the admin adds them under Holidays.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            '2026-11-30' => 'Bonifacio Day',
            '2026-12-08' => 'Feast of the Immaculate Conception',
            '2026-12-24' => 'Christmas Eve',
            '2026-12-25' => 'Christmas Day',
            '2026-12-30' => 'Rizal Day',
            '2026-12-31' => 'Last Day of the Year',
            '2027-01-01' => "New Year's Day",
            '2027-03-25' => 'Maundy Thursday',
            '2027-03-26' => 'Good Friday',
            '2027-04-09' => 'Araw ng Kagitingan',
            '2027-05-01' => 'Labor Day',
            '2027-06-12' => 'Independence Day',
            '2027-08-21' => 'Ninoy Aquino Day',
            '2027-08-30' => 'National Heroes Day',
            '2027-11-01' => "All Saints' Day",
            '2027-11-30' => 'Bonifacio Day',
            '2027-12-08' => 'Feast of the Immaculate Conception',
            '2027-12-24' => 'Christmas Eve',
            '2027-12-25' => 'Christmas Day',
            '2027-12-30' => 'Rizal Day',
            '2027-12-31' => 'Last Day of the Year',
        ];

        foreach ($holidays as $date => $name) {
            Holiday::updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
