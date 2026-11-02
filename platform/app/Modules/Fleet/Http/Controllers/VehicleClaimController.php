<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\VehicleClaimIndexRequest;
use App\Modules\Fleet\Services\VehicleClaimService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vehicle financial claims (legacy /admin/vehicle-claims). */
class VehicleClaimController extends Controller
{
    public function __construct(private readonly VehicleClaimService $claims) {}

    public function index(VehicleClaimIndexRequest $request): Response
    {
        $claim = $this->claims->build($this->userId($request), $request->validated());
        $query = array_filter($request->validated(), fn ($value): bool => filled($value));

        return Inertia::render('Fleet/Claims', [
            ...$claim,
            'csvUrl' => route('fleet.claims.csv', $query),
            'pdfUrl' => route('fleet.claims.pdf', $query),
        ]);
    }

    public function csv(VehicleClaimIndexRequest $request): StreamedResponse
    {
        return $this->claims->csv($this->claims->build($this->userId($request), $request->validated()));
    }

    public function pdf(VehicleClaimIndexRequest $request): HttpResponse
    {
        return $this->claims->pdf($this->claims->build($this->userId($request), $request->validated()));
    }

    private function userId(VehicleClaimIndexRequest $request): int
    {
        return (int) $request->user('admin')->getAuthIdentifier();
    }
}
