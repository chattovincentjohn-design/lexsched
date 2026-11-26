<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BlockedSlot;
use App\Models\Holiday;
use App\Models\Lawyer;
use App\Models\NotarialDocument;
use App\Models\NotarialEntry;
use App\Models\Service;
use App\Models\User;
use App\Services\NotarialRegister;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data: staff accounts, three lawyers, services, notarial documents and a week of appointments
 * around today's date. Every account's password is "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(HolidaySeeder::class);

        $services = collect([
            ['family', 'Family Law', 'Annulment, legal separation, custody, child support, adoption', 1500, false],
            ['criminal', 'Criminal Law', 'Complaints, subpoenas, bail, preliminary investigation', 2000, false],
            ['labor', 'Labor Law', 'Illegal dismissal, unpaid wages, NLRC and DOLE cases', 1500, false],
            ['civil', 'Civil & Property', 'Land titles, boundary disputes, contracts, collection of sum of money', 1500, false],
            ['corporate', 'Corporate', 'Business registration, SEC filings, contract review', 2500, false],
            ['notarial', 'Notarial Services', 'Affidavits, deeds of sale, SPA, travel consent for minors, certified true copies', 0, true],
        ])->mapWithKeys(fn (array $row) => [$row[0] => Service::create([
            'slug' => $row[0], 'name' => $row[1], 'description' => $row[2], 'fee' => $row[3],
            'duration_minutes' => $row[4] ? 30 : 60, 'is_notarial' => $row[4],
        ])]);

        $documents = collect([
            ['aol', 'Affidavit of Loss', 300, ['1 valid government ID', 'Details of the lost item (ID number, plate number, etc.)']],
            ['aff', 'Affidavit (general or joint)', 300, ['1 valid government ID for each affiant', 'Draft or details of the statement']],
            ['spa', 'Special Power of Attorney', 500, ['Valid ID of the principal', 'Name and ID copy of the attorney-in-fact', 'The specific acts to be authorized']],
            ['dosv', 'Deed of Sale (vehicle)', 800, ['Valid IDs of buyer and seller', 'OR/CR of the vehicle', 'Buyer and seller both present']],
            ['dosr', 'Deed of Absolute Sale (real property)', 2500, ['Valid IDs of buyer, seller and spouses', "Owner's copy of the title (TCT/OCT)", 'Latest tax declaration']],
            ['ack', 'Contract / Acknowledgment', 500, ['Valid IDs of all signatories', 'Complete, unsigned document']],
            ['minor', 'Affidavit of Consent to Travel (minor)', 400, ['Valid ID of the parent', 'PSA birth certificate of the child']],
            ['ctc', 'Certified True Copy', 100, ['Original document and a photocopy']],
        ])->mapWithKeys(fn (array $row) => [$row[0] => NotarialDocument::create(['name' => $row[1], 'fee' => $row[2], 'requirements' => $row[3]])]);

        User::create(['name' => 'Office Administrator', 'email' => 'admin@lexsched.test', 'phone' => '09170000001', 'role' => UserRole::Admin, 'password' => 'password']);
        User::create(['name' => 'Liza Manalo', 'email' => 'secretary@lexsched.test', 'phone' => '09170000002', 'role' => UserRole::Secretary, 'password' => 'password']);

        $lawyer = function (string $name, string $email, array $serviceKeys, ?string $commission, int $years) use ($services): Lawyer {
            $user = User::create(['name' => $name, 'email' => $email, 'phone' => '0917'.random_int(1000000, 9999999), 'role' => UserRole::Lawyer, 'password' => 'password']);
            $lawyer = $user->lawyer()->create([
                'roll_number' => (string) random_int(55000, 78000),
                'ibp_chapter' => 'Zamboanga del Norte',
                'years_of_practice' => $years,
                'notarial_commission_no' => $commission,
                'commission_expires_on' => $commission ? now()->endOfYear() : null,
            ]);
            $lawyer->services()->attach($services->only($serviceKeys)->pluck('id'));

            return $lawyer;
        };

        $dc = $lawyer('Juan Dela Cruz', 'delacruz@lexsched.test', ['family', 'civil', 'notarial'], '2025-014', 14);
        $ms = $lawyer('Maria Santos', 'santos@lexsched.test', ['family', 'labor', 'notarial'], '2025-022', 9);
        $rv = $lawyer('Ricardo Villanueva', 'villanueva@lexsched.test', ['criminal', 'corporate', 'civil'], null, 21);

        $me = User::create(['name' => 'Maria Clara Reyes', 'email' => 'client@lexsched.test', 'phone' => '09171234821', 'role' => UserRole::Client, 'password' => 'password']);

        $book = function (?User $client, string $name, Lawyer $lawyer, string $serviceKey, string $date, string $time, AppointmentStatus $status, array $extra = []) use ($services, $documents): Appointment {
            $document = isset($extra['doc']) ? $documents[$extra['doc']] : null;
            $copies = $extra['copies'] ?? 1;
            unset($extra['doc'], $extra['copies']);
            $paid = $extra['paid'] ?? $status === AppointmentStatus::Completed;
            unset($extra['paid']);

            return Appointment::create([
                'client_id' => $client?->id,
                'client_name' => $client?->name ?? $name,
                'client_phone' => $client?->phone ?? '0918'.random_int(1000000, 9999999),
                'client_email' => $client?->email,
                'lawyer_id' => $lawyer->id,
                'service_id' => $services[$serviceKey]->id,
                'scheduled_date' => $date,
                'start_time' => $time,
                'status' => $status,
                'fee' => $document ? $document->feeFor($copies) : $services[$serviceKey]->fee,
                'notarial_document_id' => $document?->id,
                'copies' => $copies,
                'payment_method' => $paid ? 'gcash' : 'office',
                'payment_reference' => $paid ? (string) random_int(1000000000000, 9999999999999) : null,
                'paid_at' => $paid ? Carbon::parse($date)->setTime(8, 0) : null,
                ...$extra,
            ]);
        };

        $completed = AppointmentStatus::Completed;
        $approved = AppointmentStatus::Approved;
        $pending = AppointmentStatus::Pending;

        $book($me, '', $ms, 'notarial', $this->weekday(-4), '14:00', $approved, ['doc' => 'dosv', 'concern' => 'Selling my Honda Click motorcycle to my cousin.', 'paid' => true, 'check_appeared' => true, 'check_id_presented' => true, 'check_document_complete' => true, 'id_type' => 'PhilSys National ID']);
        $book(null, 'Pedro Reyes', $ms, 'labor', $this->weekday(-3), '09:00', $completed, ['concern' => 'Dismissed without notice after 6 years as warehouse staff.', 'opposing_party' => 'Sunrise Foods Inc.', 'lawyer_notes' => 'Has payslips and the termination text message. Advised to gather SSS records.']);
        $book(null, 'Ramon Tan', $dc, 'civil', $this->weekday(-3), '10:00', $completed, ['concern' => 'Neighbor is disputing the boundary of my lot in Dipolog.', 'opposing_party' => 'Benjamin Flores']);
        $book(null, 'Carlo Go', $ms, 'labor', $this->weekday(-2), '15:00', AppointmentStatus::NoShow, ['concern' => 'Unpaid overtime for 2025.']);
        $book(null, 'Liza Uy', $rv, 'corporate', $this->weekday(-2), '10:00', $completed, ['concern' => 'Register a one-person corporation for a bakery.']);
        $book(null, 'Grace Santiago', $dc, 'family', $this->weekday(-1), '13:00', $completed, ['concern' => 'Custody arrangement after separation.', 'opposing_party' => 'Paolo Santiago']);
        $book(null, 'Jose Garcia', $rv, 'criminal', $this->weekday(-1), '11:00', $completed, ['concern' => 'Charged with reckless imprudence resulting in damage to property.']);

        $book(null, 'Ana Lim', $dc, 'family', $this->weekday(0), '09:00', $approved, ['concern' => 'Petition for recognition of foreign divorce.', 'paid' => true]);
        $book(null, 'Rosa Cruz', $ms, 'notarial', $this->weekday(0), '10:00', $approved, ['doc' => 'aff', 'copies' => 2, 'source' => 'walk_in', 'concern' => 'Joint affidavit of two disinterested persons for late birth registration.', 'check_appeared' => true]);
        $book(null, 'Jose Garcia', $rv, 'criminal', $this->weekday(0), '14:00', $approved, ['concern' => 'Follow-up: preparing the counter-affidavit.', 'mode' => 'video', 'paid' => true]);
        $book($me, '', $dc, 'family', $this->weekday(2), '11:00', $approved, ['concern' => 'Child support from my former partner, who stopped sending money in August.', 'opposing_party' => 'Antonio Bautista', 'paid' => true]);
        $book(null, 'Liza Uy', $rv, 'corporate', $this->weekday(3), '13:00', $approved, ['concern' => 'Review lease contract for the second branch.', 'paid' => true]);

        $book(null, 'Pedro Reyes', $ms, 'labor', $this->weekday(1), '10:00', $pending, ['concern' => 'Follow-up: filing the illegal dismissal complaint at the NLRC.', 'opposing_party' => 'Sunrise Foods Inc.', 'paid' => true]);
        $book(null, 'Benjamin Flores', $dc, 'civil', $this->weekday(1), '14:00', $pending, ['concern' => 'My neighbor built a fence 2 meters inside my titled lot.', 'opposing_party' => 'Ramon Tan']);
        $book(null, 'Kristine Mendoza', $ms, 'family', $this->weekday(2), '09:00', $pending, ['concern' => 'I want to know the process and cost of annulment.', 'opposing_party' => 'Mark Mendoza', 'paid' => true]);
        $book(null, 'Daniel Ocampo', $rv, 'criminal', $this->weekday(3), '09:00', $pending, ['concern' => 'Received a subpoena for an estafa complaint. The hearing is in 10 days.', 'opposing_party' => 'Vergara Trading', 'mode' => 'video', 'paid' => true]);
        $book(null, 'Sofia Navarro', $dc, 'notarial', $this->weekday(1), '16:00', $pending, ['doc' => 'aol', 'concern' => "Lost my driver's license (N03-12-345678) at the public market.", 'paid' => true]);

        // Earlier notarial register entries, then notarize the motorcycle deed through the real service.
        $earlier = [
            [$ms, 'aol', 'Ernesto Bautista', "Driver's License", 1, -5],
            [$dc, 'spa', 'Lourdes Fernandez', 'Passport', 1, -5],
            [$ms, 'ctc', 'Angela Tolentino', 'Postal ID', 3, -4],
            [$dc, 'minor', 'Ramil Castro', 'UMID', 2, -2],
        ];
        $register = app(NotarialRegister::class);

        foreach ($earlier as [$notary, $doc, $principal, $idType, $copies, $offset]) {
            // Registers in the demo continue from entries made earlier in the year.
            $docNo = max(236, $register->nextDocumentNumber($notary->id, (int) now()->year));
            NotarialEntry::create([
                'lawyer_id' => $notary->id, 'series' => (int) now()->year, 'doc_no' => $docNo,
                'page_no' => $register->pageFor($docNo), 'book_no' => $register->bookFor($docNo),
                'notarized_on' => $this->weekday($offset), 'document_name' => $documents[$doc]->name,
                'principal_name' => $principal, 'id_type' => $idType, 'copies' => $copies, 'fee' => $documents[$doc]->feeFor($copies),
            ]);
        }

        $register->record(Appointment::where('client_id', $me->id)->where('notarial_document_id', $documents['dosv']->id)->first());
        NotarialEntry::latest('id')->first()->update(['notarized_on' => $this->weekday(-4)]);

        foreach (['09:00', '10:00', '11:00'] as $time) {
            BlockedSlot::create(['lawyer_id' => $dc->id, 'date' => $this->weekday(4), 'start_time' => $time, 'reason' => 'Court hearing']);
        }
        BlockedSlot::create(['lawyer_id' => $ms->id, 'date' => $this->weekday(3), 'start_time' => '13:00', 'reason' => 'Mediation']);
        BlockedSlot::create(['lawyer_id' => $ms->id, 'date' => $this->weekday(3), 'start_time' => '14:00', 'reason' => 'Mediation']);
        BlockedSlot::create(['lawyer_id' => $rv->id, 'date' => $this->weekday(5), 'start_time' => null, 'reason' => 'On leave']);

        AuditLog::query()->update(['created_at' => now()->subHours(3)]);
    }

    /**
     * The office day $offset working days from today (negative for past days). Skips weekends and holidays.
     */
    private function weekday(int $offset): string
    {
        $holidays = Holiday::dates();
        $isOfficeDay = fn (Carbon $day) => ! $day->isWeekend() && ! in_array($day->toDateString(), $holidays, true);
        $day = today();

        while (! $isOfficeDay($day)) {
            $day->addDay();
        }

        for ($i = 0; $i < abs($offset); $i++) {
            do {
                $offset < 0 ? $day->subDay() : $day->addDay();
            } while (! $isOfficeDay($day));
        }

        return $day->toDateString();
    }
}
