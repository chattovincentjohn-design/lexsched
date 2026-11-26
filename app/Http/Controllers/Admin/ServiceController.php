<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotarialDocument;
use App\Models\Service;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services', [
            'services' => Service::query()->withCount('lawyers')->orderBy('id')->get(),
            'documents' => NotarialDocument::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $service->update($data);
        AuditLogger::log('service_updated', "Updated {$service->name}: fee ₱".number_format((float) $service->fee, 2).($service->is_active ? '' : ' (inactive)'), $service);

        return back()->with('status', "{$service->name} saved.");
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $document = NotarialDocument::create($this->documentData($request));
        AuditLogger::log('document_added', "Added notarial document {$document->name}", $document);

        return back()->with('status', "{$document->name} added.");
    }

    public function updateDocument(Request $request, NotarialDocument $document): RedirectResponse
    {
        $document->update($this->documentData($request));
        AuditLogger::log('document_updated', "Updated notarial document {$document->name}: fee ₱".number_format((float) $document->fee, 2), $document);

        return back()->with('status', "{$document->name} saved.");
    }

    /**
     * @return array{name: string, fee: float, requirements: list<string>, is_active: bool}
     */
    private function documentData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'requirements' => ['required', 'string', 'max:1000'],
        ]);

        return [
            'name' => $data['name'],
            'fee' => (float) $data['fee'],
            'requirements' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['requirements'])))),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
