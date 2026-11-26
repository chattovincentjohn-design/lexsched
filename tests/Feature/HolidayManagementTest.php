<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeOnOfficeMorning();
        $this->seedOffice();
    }

    public function test_admin_adds_a_holiday_and_clients_can_no_longer_book_it(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->post(route('admin.holidays.store'), ['date' => '2026-12-02', 'name' => 'Dipolog City Fiesta'])
            ->assertSessionHas('status');

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['date' => '2026-12-02']))
            ->assertSessionHasErrors('time');
    }

    public function test_adding_a_holiday_warns_about_existing_appointments(): void
    {
        Appointment::factory()->create(['lawyer_id' => $this->lawyer->id, 'service_id' => $this->family->id, 'scheduled_date' => '2026-12-03']);

        $this->actingAs($this->user(UserRole::Admin))
            ->post(route('admin.holidays.store'), ['date' => '2026-12-03', 'name' => 'Special non-working day']);

        $this->assertStringContainsString('1 existing appointment on that day still need to be moved', session('status'));
    }

    public function test_removing_a_holiday_reopens_the_day(): void
    {
        $holiday = Holiday::where('date', '2026-12-08')->sole();

        $this->actingAs($this->user(UserRole::Admin))->delete(route('admin.holidays.destroy', $holiday))->assertSessionHas('status');

        $this->actingAs($this->user(UserRole::Client))
            ->post(route('client.book.store'), $this->bookingPayload(['date' => '2026-12-08']))
            ->assertSessionHasNoErrors();
    }

    public function test_duplicate_dates_are_refused_and_only_admins_manage_holidays(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->post(route('admin.holidays.store'), ['date' => '2026-12-08', 'name' => 'Again'])
            ->assertSessionHasErrors('date');

        $this->actingAs($this->user(UserRole::Secretary))->get(route('admin.holidays.index'))->assertForbidden();
        $this->actingAs($this->user(UserRole::Admin))->get(route('admin.holidays.index'))->assertOk()->assertSee('Feast of the Immaculate Conception');
    }
}
