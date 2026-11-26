<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\NotarialEntry;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records notarized documents in each notary public's register and assigns the
 * Doc. No. / Page No. / Book No. printed on the notarial certificate.
 */
class NotarialRegister
{
    public function record(Appointment $appointment): NotarialEntry
    {
        if (! $appointment->isNotarial()) {
            throw new DomainException('This appointment is not for a notarial service.');
        }

        if ($appointment->status !== AppointmentStatus::Approved) {
            throw new DomainException('Only approved appointments can be notarized.');
        }

        if (! $appointment->notarialChecklistComplete()) {
            throw new DomainException('Complete the notarial checklist and select the ID presented first.');
        }

        if (! $appointment->lawyer->hasValidNotarialCommission()) {
            throw new DomainException("{$appointment->lawyer->name} has no valid notarial commission.");
        }

        return DB::transaction(function () use ($appointment): NotarialEntry {
            // Lock the notary's row so two documents can never receive the same number.
            Lawyer::query()->whereKey($appointment->lawyer_id)->lockForUpdate()->first();

            $series = (int) now()->year;
            $docNo = $this->nextDocumentNumber($appointment->lawyer_id, $series);

            $entry = NotarialEntry::create([
                'appointment_id' => $appointment->id,
                'lawyer_id' => $appointment->lawyer_id,
                'series' => $series,
                'doc_no' => $docNo,
                'page_no' => $this->pageFor($docNo),
                'book_no' => $this->bookFor($docNo),
                'notarized_on' => today(),
                'document_name' => $appointment->notarialDocument->name,
                'principal_name' => $appointment->client_name,
                'id_type' => $appointment->id_type,
                'copies' => $appointment->copies ?? 1,
                'fee' => $appointment->fee,
            ]);

            $appointment->update([
                'status' => AppointmentStatus::Completed,
                'paid_at' => $appointment->paid_at ?? now(),
                'payment_method' => $appointment->paid_at ? $appointment->payment_method : 'cash',
            ]);

            AuditLogger::log('notarized', "Notarized {$entry->document_name} for {$entry->principal_name} ({$entry->certificateLine()})", $appointment);

            return $entry;
        });
    }

    public function nextDocumentNumber(int $lawyerId, int $series): int
    {
        return (int) NotarialEntry::query()->where('lawyer_id', $lawyerId)->where('series', $series)->max('doc_no') + 1;
    }

    public function pageFor(int $docNo): int
    {
        return (int) ceil($docNo / config('lexsched.register_entries_per_page'));
    }

    public function bookFor(int $docNo): string
    {
        return $this->roman((int) ceil($docNo / config('lexsched.register_entries_per_book')));
    }

    private function roman(int $number): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';

        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $result .= $symbol;
                $number -= $value;
            }
        }

        return $result;
    }
}
