<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use App\Modules\Wells\Services\ActivityMediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rules the legacy Api\Wells\ActivityMediaController::store() validated inline. */
class StoreActivityMediaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in([ActivityMediaService::TYPE_READING, ActivityMediaService::TYPE_NOTIFICATION])],
            'activity_oid' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'photo_local_ids' => ['required', 'array', 'min:1', 'max:4'],
            'photo_local_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
