<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isNotarial = (bool) Service::find($this->input('service_id'))?->is_notarial;

        return [
            'service_id' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'lawyer_id' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', Rule::in(config('lexsched.slots'))],
            'mode' => ['nullable', Rule::in(['in_office', 'video'])],
            'concern' => [$isNotarial ? 'nullable' : 'required', 'string', 'max:2000'],
            'opposing_party' => ['nullable', 'string', 'max:150'],
            'notarial_document_id' => [Rule::requiredIf($isNotarial), 'nullable', Rule::exists('notarial_documents', 'id')->where('is_active', true)],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'payment_method' => ['required', Rule::in(['gcash', 'maya', 'office'])],
            'payment_reference' => ['nullable', 'required_if:payment_method,gcash,maya', 'string', 'max:40'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:'.config('lexsched.max_upload_kb')],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'concern.required' => 'Please describe your concern so the lawyer can prepare.',
            'consent.accepted' => 'Please give your consent under the Data Privacy Act to continue.',
            'payment_reference.required_if' => 'Enter the reference number from your GCash or Maya receipt.',
            'date.after_or_equal' => 'Please choose today or a later date.',
        ];
    }
}
