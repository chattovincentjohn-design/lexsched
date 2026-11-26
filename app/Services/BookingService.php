<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Lawyer;
use App\Models\NotarialDocument;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates appointments for online bookings and walk-ins after re-checking that the slot is free.
 */
class BookingService
{
    public function __construct(private AvailabilityService $availability, private AppointmentWorkflow $workflow) {}

    /**
     * @param  array{service_id: int, lawyer_id?: int|string|null, date: string, time: string, mode?: string|null,
     *     concern?: string|null, opposing_party?: string|null, notarial_document_id?: int|null, copies?: int|null,
     *     payment_method?: string|null, payment_reference?: string|null, client_name: string,
     *     client_phone?: string|null, client_email?: string|null, client_id?: int|null}  $data
     */
    public function book(array $data, string $source = 'online', ?UploadedFile $attachment = null): Appointment
    {
        $service = Service::query()->active()->findOrFail($data['service_id']);
        $availability = $source === 'walk_in' ? $this->availability->forWalkIns() : $this->availability;
        $lawyer = $this->pickLawyer($availability, $service, $data['lawyer_id'] ?? null, $data['date'], $data['time']);
        $document = $service->is_notarial ? NotarialDocument::query()->active()->findOrFail($data['notarial_document_id']) : null;
        $copies = $document ? (int) ($data['copies'] ?? 1) : 1;

        try {
            $appointment = DB::transaction(function () use ($data, $service, $lawyer, $document, $copies, $source, $attachment): Appointment {
                $appointment = Appointment::create([
                    'client_id' => $data['client_id'] ?? null,
                    'client_name' => $data['client_name'],
                    'client_phone' => $data['client_phone'] ?? null,
                    'client_email' => $data['client_email'] ?? null,
                    'lawyer_id' => $lawyer->id,
                    'service_id' => $service->id,
                    'scheduled_date' => $data['date'],
                    'start_time' => $data['time'],
                    'status' => $source === 'walk_in' ? AppointmentStatus::Approved : AppointmentStatus::Pending,
                    'mode' => $service->is_notarial ? 'in_office' : ($data['mode'] ?? 'in_office'),
                    'source' => $source,
                    'concern' => $data['concern'] ?? null,
                    'opposing_party' => $service->is_notarial ? null : ($data['opposing_party'] ?? null),
                    'fee' => $document ? $document->feeFor($copies) : $service->fee,
                    'payment_method' => $data['payment_method'] ?? 'office',
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'notarial_document_id' => $document?->id,
                    'copies' => $copies,
                ]);

                if ($attachment) {
                    $appointment->attachments()->create([
                        'original_name' => $attachment->getClientOriginalName(),
                        'path' => $attachment->store("attachments/{$appointment->reference}", config('lexsched.attachments_disk')),
                        'size' => $attachment->getSize(),
                    ]);
                }

                return $appointment;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['time' => 'Someone just booked that time. Please choose another.']);
        }

        $appointment->load(['lawyer.user', 'service', 'notarialDocument']);
        AuditLogger::log($source === 'walk_in' ? 'walk_in' : 'booked', ($source === 'walk_in' ? 'Added walk-in ' : 'New booking request ')."{$appointment->reference} ({$appointment->client_name}, {$service->name}, {$appointment->scheduleLabel()})", $appointment);
        $this->workflow->notify($appointment, $source === 'walk_in' ? 'approved' : 'received');

        return $appointment;
    }

    private function pickLawyer(AvailabilityService $availability, Service $service, int|string|null $lawyerId, string $date, string $time): Lawyer
    {
        if (filled($lawyerId) && $lawyerId !== 'any') {
            $lawyer = $availability->lawyersFor($service)->firstWhere('id', (int) $lawyerId);

            if (! $lawyer) {
                throw ValidationException::withMessages(['lawyer_id' => 'That lawyer does not handle this service.']);
            }

            if (! $availability->isSlotFree($lawyer, $date, $time)) {
                throw ValidationException::withMessages(['time' => 'That time is no longer available. Please choose another.']);
            }

            return $lawyer;
        }

        $freeLawyerIds = $availability->openSlotsForService($service, null, $date)->get($time, []);

        if ($freeLawyerIds === []) {
            throw ValidationException::withMessages(['time' => 'No lawyer is free at that time. Please choose another.']);
        }

        return Lawyer::with('user')->findOrFail($freeLawyerIds[0]);
    }
}
