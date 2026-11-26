<?php

namespace App\Http\Controllers;

use App\Models\AppointmentAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(AppointmentAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->appointment);

        return Storage::disk(config('lexsched.attachments_disk'))->download($attachment->path, $attachment->original_name);
    }
}
