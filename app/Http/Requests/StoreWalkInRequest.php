<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalkInRequest extends FormRequest
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
            'client_name' => ['required', 'string', 'max:120'],
            'client_phone' => ['nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'client_email' => ['nullable', 'email', 'max:150'],
            'service_id' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'lawyer_id' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', Rule::in(config('lexsched.slots'))],
            'concern' => ['nullable', 'string', 'max:2000'],
            'opposing_party' => ['nullable', 'string', 'max:150'],
            'notarial_document_id' => [Rule::requiredIf($isNotarial), 'nullable', Rule::exists('notarial_documents', 'id')->where('is_active', true)],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['client_phone.regex' => 'Use a mobile number like 09171234567.'];
    }
}
