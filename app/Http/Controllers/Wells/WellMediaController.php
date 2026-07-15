<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Services\Wells\WellMediaService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WellMediaController extends Controller
{
    public function __construct(private readonly WellMediaService $service) {}

    public function show(int $media): StreamedResponse
    {
        return $this->service->response($media);
    }

    public function destroy(int $well, int $media): RedirectResponse
    {
        $this->service->delete($well, $media);

        return back()->with('success', 'تم حذف الصورة بنجاح');
    }
}
