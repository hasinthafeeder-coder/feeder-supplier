<?php

namespace App\Http\Requests\Notification;

use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Feeder\Core\Support\BusinessTimezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotificationRequest extends FormRequest
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
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_map(
                static fn (NotificationType $type): string => $type->value,
                NotificationType::supplierManagedCases()
            ))],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'banner' => ['nullable', 'file', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->filled('expires_at') && ! $this->filled('publish_at')) {
                $validator->errors()->add(
                    'publish_at',
                    'Set a publish time when an expiry is provided.'
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
