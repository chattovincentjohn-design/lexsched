<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Lawyer;
use App\Models\Service;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * JSON used by the booking and walk-in forms to show open dates and times.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $request->validate([
            'service' => ['required', 'exists:services,id'],
            'lawyer' => ['nullable', 'string'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($request->user()->hasRole(UserRole::Secretary, UserRole::Admin)) {
            $availability = $availability->forWalkIns();
        }

        $service = Service::findOrFail($request->integer('service'));
        $lawyers = $availability->lawyersFor($service);
        $lawyer = filled($request->input('lawyer')) && $request->input('lawyer') !== 'any'
            ? $lawyers->firstWhere('id', (int) $request->input('lawyer'))
            : null;

        $response = [
            'lawyers' => $lawyers->map(fn (Lawyer $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'services' => $item->services()->pluck('name'),
                'notary' => $item->hasValidNotarialCommission(),
            ])->values(),
        ];

        if ($request->filled('month')) {
            $response['days'] = $availability->openDays($service, $lawyer, Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth());
        }

        if ($request->filled('date')) {
            $open = $availability->openSlotsForService($service, $lawyer, $request->input('date'));
            $response['slots'] = collect($availability->slots())->map(fn (string $time) => [
                'time' => $time,
                'label' => Carbon::createFromFormat('H:i', $time)->format('g:i A'),
                'open' => $open->has($time),
            ])->values();
        }

        return response()->json($response);
    }
}
