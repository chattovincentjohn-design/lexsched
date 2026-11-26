<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function auditLog(): View
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->latest('created_at')->paginate(30)]);
    }

    public function exportAppointments(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $appointments = Appointment::query()
            ->whereBetween('scheduled_date', [$data['from'], $data['to']])
            ->with(['lawyer.user', 'service', 'notarialDocument'])
            ->chronological()
            ->get();

        return response()->streamDownload(function () use ($appointments): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Time', 'Client', 'Phone', 'Service', 'Document', 'Lawyer', 'Mode', 'Source', 'Status', 'Fee', 'Paid', 'Payment method', 'OR No.']);

            foreach ($appointments as $a) {
                fputcsv($out, [
                    $a->reference, $a->scheduled_date->toDateString(), $a->start_time, $a->client_name, $a->client_phone,
                    $a->service->name, $a->notarialDocument?->name, $a->lawyer->name, $a->modeLabel(), $a->source,
                    $a->status->label(), $a->fee, $a->isPaid() ? 'Yes' : 'No', Appointment::PAYMENT_METHODS[$a->payment_method] ?? $a->payment_method, $a->official_receipt_no,
                ]);
            }

            fclose($out);
        }, "appointments-{$data['from']}-to-{$data['to']}.csv", ['Content-Type' => 'text/csv']);
    }
}
