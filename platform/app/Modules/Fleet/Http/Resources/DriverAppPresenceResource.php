<?php

namespace App\Modules\Fleet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverAppPresenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recorded' => true,
            'last_seen_at' => $this->resource['last_seen_at'],
            'latest_version' => $this->resource['latest_version'],
            'latest_build' => $this->resource['latest_build'],
            'update_required' => $this->resource['update_required'],
        ];
    }
}
