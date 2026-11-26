<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Lawyer;
use App\Models\NotarialDocument;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected Service $family;

    protected Service $notarial;

    protected NotarialDocument $affidavit;

    protected Lawyer $lawyer;

    protected Lawyer $notary;

    /**
     * A Tuesday office day used as "now" in tests: 2026-12-01 08:00 Manila time.
     */
    protected function freezeOnOfficeMorning(): Carbon
    {
        $now = Carbon::parse('2026-12-01 08:00', 'Asia/Manila');
        $this->travelTo($now);

        return $now;
    }

    protected function seedOffice(): void
    {
        $this->family = Service::factory()->create(['slug' => 'family', 'name' => 'Family Law', 'fee' => 1500]);
        $this->notarial = Service::factory()->notarial()->create(['slug' => 'notarial']);
        $this->affidavit = NotarialDocument::factory()->create(['name' => 'Affidavit of Loss', 'fee' => 300]);
        $this->lawyer = Lawyer::factory()->offering($this->family)->create();
        $this->notary = Lawyer::factory()->notary()->offering([$this->family, $this->notarial])->create();
    }

    protected function user(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function bookingPayload(array $overrides = []): array
    {
        return [
            'service_id' => $this->family->id,
            'lawyer_id' => (string) $this->lawyer->id,
            'date' => '2026-12-02',
            'time' => '10:00',
            'mode' => 'in_office',
            'concern' => 'Child support from my former partner.',
            'opposing_party' => 'Antonio Bautista',
            'payment_method' => 'office',
            'consent' => '1',
            ...$overrides,
        ];
    }
}
