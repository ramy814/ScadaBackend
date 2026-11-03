<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncNotificationsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notifications = $this->input('notifications', []);
        if (! is_array($notifications)) {
            return;
        }

        $this->merge([
            'notifications' => array_map(
                fn (mixed $record): mixed => is_array($record)
                    ? array_change_key_case($record, CASE_UPPER)
                    : $record,
                $notifications,
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'notifications' => ['required', 'array', 'min:1', 'max:500'],
            'notifications.*.LOCAL_ID' => ['nullable', 'string', 'max:64', 'distinct'],
            'notifications.*.WELL_OID' => ['required', 'integer'],
            'notifications.*.NOTIFICATION_TYPE_OID' => ['required', 'integer'],
            'notifications.*.MESSAGE' => ['required', 'string', 'max:2000'],
        ];
    }
}
