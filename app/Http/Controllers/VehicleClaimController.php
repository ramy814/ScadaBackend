<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleClaimIndexRequest;
use App\Services\VehicleClaimService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleClaimController extends Controller
{
    public function index(VehicleClaimIndexRequest $request, VehicleClaimService $claims): Response
    {
        return Inertia::render('Admin/VehicleClaims', $claims->build($this->userId(), $request->validated()));
    }

    public function csv(VehicleClaimIndexRequest $request, VehicleClaimService $claims): StreamedResponse
    {
        $claim = $claims->build($this->userId(), $request->validated());
        $lines = $claims->csvRows($claim);

        return response()->streamDownload(function () use ($lines): void {
            $handle = fopen('php://output', 'wb');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            foreach ($lines as $line) {
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, $this->fileName($claim, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(VehicleClaimIndexRequest $request, VehicleClaimService $claims): HttpResponse
    {
        $claim = $claims->build($this->userId(), $request->validated());
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            // DejaVu Sans shapes Arabic itself; automatic script tagging reverses plates such as "3-1234".
            'default_font' => 'dejavusans',
        ]);
        $mpdf->WriteHTML(view('admin.vehicle-claims-pdf', [...$claim, 'generatedAt' => now()])->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName($claim, 'pdf').'"',
        ]);
    }

    private function userId(): int
    {
        return (int) Auth::guard('admin')->id();
    }

    private function fileName(array $claim, string $extension): string
    {
        $period = $claim['filters']['month']
            ?? $claim['filters']['date_from'].'_'.$claim['filters']['date_to'];

        return 'vehicle-claims-'.$period.'-'.now()->format('Ymd_His').'.'.$extension;
    }
}
