<?php

namespace App\Http\Requests\Notification;

use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Services\Notification\NotificationPublicationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(NotificationPublicationService::PERMISSION_VIEW) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', Rule::in(array_map(
                static fn (NotificationType $type): string => $type->value,
                NotificationType::supplierManagedCases()
            ))],
            'schedule' => ['nullable', 'string', Rule::in(['scheduled', 'active', 'expired'])],
        ];
    }
}
