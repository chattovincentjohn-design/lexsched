<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use App\Models\NotarialEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotarialRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $notaries = Lawyer::query()->whereNotNull('notarial_commission_no')->with('user')->get();
        $series = $request->integer('series', (int) now()->year);
        $notaryId = $request->integer('notary') ?: $notaries->first()?->id;

        $entries = $this->query($series, $notaryId)->orderByDesc('doc_no')->paginate(25)->withQueryString();

        return view('staff.register', [
            'entries' => $entries,
            'notaries' => $notaries,
            'notaryId' => $notaryId,
            'series' => $series,
            'totalFees' => $this->query($series, $notaryId)->sum('fee'),
            'totalEntries' => $this->query($series, $notaryId)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $series = $request->integer('series', (int) now()->year);
        $notary = Lawyer::with('user')->findOrFail($request->integer('notary'));
        $entries = $this->query($series, $notary->id)->orderBy('doc_no')->get();

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Doc. No.', 'Page No.', 'Book No.', 'Series', 'Date', 'Document', 'Principal', 'ID presented', 'Copies', 'Fee']);

            foreach ($entries as $entry) {
                fputcsv($out, [$entry->doc_no, $entry->page_no, $entry->book_no, $entry->series, $entry->notarized_on->toDateString(), $entry->document_name, $entry->principal_name, $entry->id_type, $entry->copies, $entry->fee]);
            }

            fclose($out);
        }, "notarial-register-{$series}-".str($notary->user->name)->slug().'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<NotarialEntry>
     */
    private function query(int $series, ?int $notaryId): Builder
    {
        return NotarialEntry::query()->where('series', $series)->where('lawyer_id', $notaryId);
    }
}
