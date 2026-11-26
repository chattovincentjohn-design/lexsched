<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\Client;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Lawyer\ScheduleController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RescheduleController;
use App\Http\Controllers\Staff;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/webhooks/paymongo', PayMongoWebhookController::class)->name('webhooks.paymongo');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/availability', AvailabilityController::class)->name('availability');
    Route::get('/appointments/{appointment}/receipt', ReceiptController::class)->name('appointments.receipt');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    Route::middleware('role:client')->name('client.')->group(function (): void {
        Route::get('/book', [Client\BookingController::class, 'create'])->name('book');
        Route::post('/book', [Client\BookingController::class, 'store'])->name('book.store');
        Route::get('/my/appointments', [Client\AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/my/appointments/{appointment}', [Client\AppointmentController::class, 'show'])->name('appointments.show');
        Route::post('/my/appointments/{appointment}/pay', [Client\AppointmentController::class, 'pay'])->name('appointments.pay');
        Route::get('/my/appointments/{appointment}/reschedule', [RescheduleController::class, 'edit'])->name('appointments.reschedule');
        Route::put('/my/appointments/{appointment}/reschedule', [RescheduleController::class, 'update'])->name('appointments.reschedule.update');
        Route::post('/my/appointments/{appointment}/cancel', [Client\AppointmentController::class, 'cancel'])->name('appointments.cancel');
    });

    Route::middleware('role:secretary,admin')->prefix('staff')->name('staff.')->group(function (): void {
        Route::get('/appointments', [Staff\AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{appointment}/reschedule', [RescheduleController::class, 'edit'])->name('appointments.reschedule');
        Route::put('/appointments/{appointment}/reschedule', [RescheduleController::class, 'update'])->name('appointments.reschedule.update');
        Route::post('/appointments/{appointment}/approve', [Staff\AppointmentController::class, 'approve'])->name('appointments.approve');
        Route::post('/appointments/{appointment}/decline', [Staff\AppointmentController::class, 'decline'])->name('appointments.decline');
        Route::post('/appointments/{appointment}/cancel', [Staff\AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('/appointments/{appointment}/complete', [Staff\AppointmentController::class, 'complete'])->name('appointments.complete');
        Route::post('/appointments/{appointment}/no-show', [Staff\AppointmentController::class, 'noShow'])->name('appointments.no-show');
        Route::post('/appointments/{appointment}/payment', [Staff\AppointmentController::class, 'recordPayment'])->name('appointments.payment');
        Route::post('/appointments/{appointment}/checklist', [Staff\AppointmentController::class, 'updateChecklist'])->name('appointments.checklist');
        Route::post('/appointments/{appointment}/notarize', [Staff\AppointmentController::class, 'notarize'])->name('appointments.notarize');
        Route::get('/calendar', Staff\CalendarController::class)->name('calendar');
        Route::get('/walk-in', [Staff\WalkInController::class, 'create'])->name('walk-in.create');
        Route::post('/walk-in', [Staff\WalkInController::class, 'store'])->name('walk-in.store');
        Route::get('/notarial-register', [Staff\NotarialRegisterController::class, 'index'])->name('register');
        Route::get('/notarial-register/export', [Staff\NotarialRegisterController::class, 'export'])->name('register.export');
    });

    Route::middleware('role:lawyer')->prefix('lawyer')->name('lawyer.')->group(function (): void {
        Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
        Route::post('/blocks', [ScheduleController::class, 'block'])->name('blocks.store');
        Route::delete('/blocks/{block}', [ScheduleController::class, 'unblock'])->name('blocks.destroy');
        Route::patch('/appointments/{appointment}/notes', [ScheduleController::class, 'saveNotes'])->name('appointments.notes');
        Route::post('/appointments/{appointment}/complete', [ScheduleController::class, 'complete'])->name('appointments.complete');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/services', [Admin\ServiceController::class, 'index'])->name('services.index');
        Route::put('/services/{service}', [Admin\ServiceController::class, 'update'])->name('services.update');
        Route::post('/notarial-documents', [Admin\ServiceController::class, 'storeDocument'])->name('documents.store');
        Route::put('/notarial-documents/{document}', [Admin\ServiceController::class, 'updateDocument'])->name('documents.update');
        Route::get('/holidays', [Admin\HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays', [Admin\HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('/holidays/{holiday}', [Admin\HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::get('/audit-log', [Admin\ReportController::class, 'auditLog'])->name('audit');
        Route::get('/reports/appointments.csv', [Admin\ReportController::class, 'exportAppointments'])->name('reports.appointments');
    });
});
