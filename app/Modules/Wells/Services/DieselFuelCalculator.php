<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Models\Well;

class DieselFuelCalculator
{
    public function tankCapacityLiters(Well $well): ?float
    {
        $shape = strtolower((string) ($well->getAttribute('TANK_SHAPE') ?? $well->getAttribute('tank_shape') ?? ''));
        $diameter = (float) ($well->getAttribute('TANK_DIAMETER') ?? $well->getAttribute('tank_diameter') ?? 0);
        $tankHeight = (float) ($well->getAttribute('TANK_HEIGHT') ?? $well->getAttribute('tank_height') ?? 0);
        $maximumFuelHeight = str_contains($shape, 'أفقي') || str_contains($shape, 'افقي') || str_contains($shape, 'horizontal')
            ? $diameter
            : $tankHeight;

        return $maximumFuelHeight <= 0
            ? null
            : $this->litersForWell($well, $maximumFuelHeight * 100);
    }

    public function litersForWell(Well $well, mixed $dieselHeightCm): ?float
    {
        if ($dieselHeightCm === null || $dieselHeightCm === '' || ! is_numeric($dieselHeightCm)) {
            return null;
        }

        $heightMeters = max(0.0, (float) $dieselHeightCm / 100);
        $diameter = (float) ($well->getAttribute('TANK_DIAMETER') ?? $well->getAttribute('tank_diameter') ?? 0);
        $tankHeight = (float) ($well->getAttribute('TANK_HEIGHT') ?? $well->getAttribute('tank_height') ?? 0);

        if ($diameter <= 0 || $tankHeight <= 0) {
            return null;
        }

        $shape = strtolower((string) ($well->getAttribute('TANK_SHAPE') ?? $well->getAttribute('tank_shape') ?? ''));
        $cubicMeters = match (true) {
            str_contains($shape, 'أفقي') || str_contains($shape, 'افقي') || str_contains($shape, 'horizontal') => $this->horizontalCylinder($diameter, $tankHeight, $heightMeters),
            str_contains($shape, 'مكعب') || str_contains($shape, 'cube') => $diameter * $diameter * min($heightMeters, $tankHeight),
            str_contains($shape, 'عمودي') || str_contains($shape, 'vertical') => pi() * ($diameter / 2) ** 2 * min($heightMeters, $tankHeight),
            default => null,
        };

        return $cubicMeters === null ? null : round($cubicMeters * 1000, 2);
    }

    private function horizontalCylinder(float $diameter, float $length, float $height): float
    {
        $radius = $diameter / 2;
        $height = max(0.0, min($height, $diameter));

        if ($height >= $diameter) {
            return pi() * $radius ** 2 * $length;
        }

        $area = $radius ** 2 * acos(($radius - $height) / $radius)
            - ($radius - $height) * sqrt(max(0.0, 2 * $radius * $height - $height ** 2));

        return $area * $length;
    }
}
