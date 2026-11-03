<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => [$this->route('type') === 'locations' ? 'required' : 'prohibited', 'in:origin,destination,both'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function attributesForSave(): array
    {
        return [...$this->validated(), 'sort_order' => (int) ($this->validated()['sort_order'] ?? 0)];
    }
}
