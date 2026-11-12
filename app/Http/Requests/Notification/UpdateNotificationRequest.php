<?php

namespace App\Http\Requests\Notification;

use Feeder\Core\Services\Notification\NotificationPublicationService;
use Feeder\Core\Support\BusinessTimezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(NotificationPublicationService::PERMISSION_CREATE) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'publish_at' => $this->normalizeDateTimeInput($this->input('publish_at')),
            'expires_at' => $this->normalizeDateTimeInput($this->input('expires_at')),
            'remove_banner' => $this->boolean('remove_banner'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'publish_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'banner' => ['nullable', 'file', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'remove_banner' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('remove_banner') && $this->hasFile('banner')) {
                $validator->errors()->add(
                    'banner',
                    'Upload a new banner or remove the existing one, not both.'
                );
            }
        });
    }

    private function normalizeDateTimeInput(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return BusinessTimezone::parseWallClockToUtc($value)->format('Y-m-d H:i:s');
    }
}