<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Services\Wells\ActivityMediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityMediaController extends Controller
{
    public function __construct(private readonly ActivityMediaService $service) {}

    public function show(int $media): StreamedResponse
    {
        return $this->service->response($media);
    }
}
