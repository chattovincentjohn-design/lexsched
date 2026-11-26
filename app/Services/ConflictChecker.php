<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Str;

/**
 * Flags possible conflicts of interest: the opposing party is already a client of the firm,
 * or an existing client named this client as their own opposing party.
 */
class ConflictChecker
{
    /**
     * @return list<string>
     */
    public function check(Appointment $appointment): array
    {
        $client = $this->normalize($appointment->client_name);
        $opposing = $this->normalize($appointment->opposing_party);
        $others = Appointment::query()->whereKeyNot($appointment->id)->with('service')->get(['id', 'reference', 'client_name', 'opposing_party', 'service_id']);
        $findings = [];

        if ($opposing !== '') {
            $others->filter(fn (Appointment $other) => $this->normalize($other->client_name) === $opposing)
                ->pluck('client_name')
                ->unique()
                ->each(function (string $name) use (&$findings): void {
                    $findings[] = "{$name} (the opposing party) is an existing client of the firm.";
                });
        }

        $others->filter(fn (Appointment $other) => $this->normalize($other->opposing_party) === $client
                && $this->normalize($other->client_name) !== $client)
            ->each(function (Appointment $other) use (&$findings, $appointment): void {
                $findings[] = "{$other->client_name} named {$appointment->client_name} as the opposing party in {$other->reference} ({$other->service->name}).";
            });

        return array_values(array_unique($findings));
    }

    public function clientCount(): int
    {
        return Appointment::query()->distinct()->count('client_name');
    }

    private function normalize(?string $name): string
    {
        return Str::of($name ?? '')->lower()->replaceMatches('/[^a-z ]/', '')->squish()->value();
    }
}
