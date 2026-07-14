<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\Well;

class DieselFuelCalculator
{
    public function litersForWell(Well $well, mixed $dieselHeightCm): ?float
    {
        $heightMeters = ((float) ($dieselHeightCm ?? 0)) / 100;
        $diameter = (float) ($well->getAttribute('TANK_DIAMETER') ?? $well->getAttribute('tank_diameter') ?? 0);
        $tankHeight = (float) ($well->getAttribute('TANK_HEIGHT') ?? $well->getAttribute('tank_height') ?? 0);

        if ($heightMeters <= 0 || $diameter <= 0 || $tankHeight <= 0) {
            return null;
        }

        $shape = strtolower((string) ($well->getAttribute('TANK_SHAPE') ?? $well->getAttribute('tank_shape') ?? ''));
        $cubicMeters = match (true) {
            str_contains($shape, 'أفقي') || str_contains($shape, 'افقي') || str_contains($shape, 'horizontal') => $this->horizontalCylinder($diameter, $tankHeight, $heightMeters),
            str_contains($shape, 'مكعب') || str_contains($shape, 'cube') => $diameter * $diameter * min($heightMeters, $tankHeight),
            default => pi() * ($diameter / 2) ** 2 * min($heightMeters, $tankHeight),
        };

        return round($cubicMeters * 1000, 2);
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
