<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Trip;
use DateTimeInterface;

class VirtualFuelService
{
    private const LITERS_PER_KILOMETER = 0.12;

    private const MAX_ACCURACY_METERS = 50.0;

    private const MIN_QUALITY_DATA_COVERAGE = 0.8;

    private const MAX_SPEED_METERS_PER_SECOND = 55.0;

    private const MAX_SEGMENT_GAP_SECONDS = 60;

    private const SEVERE_COVERAGE_GAP_SECONDS = 1200;

    private const MAX_TRIP_MINUTES = 720;

    private const MAX_DISTANCE_KM_WITHOUT_REVIEW = 200.0;

    private const MAX_POSITION_AGE_SECONDS = 30;

    private const FUTURE_POSITION_TOLERANCE_SECONDS = 10;

    public function estimateLiters(Trip $trip): ?float
    {
        return $this->estimateFromMetrics($trip, $this->metrics($trip));
    }

    public function estimateFromMetrics(Trip $trip, array $metrics): ?float
    {
        if (array_intersect(
            [
                'no_gps_points',
                'no_valid_points',
                'invalid_duration',
                'duration_over_12h',
                'distance_over_policy',
                'tracking_coverage_gap',
            ],
            $metrics['quality_flags'] ?? [],
        )) {
            return null;
        }

        return $this->estimateProvisionalFromMetrics($metrics);
    }

    public function estimateProvisionalFromMetrics(array $metrics): ?float
    {
        if (array_intersect(
            ['no_gps_points', 'no_valid_points', 'invalid_duration'],
            $metrics['quality_flags'] ?? [],
        )) {
            return null;
        }

        $distanceKm = $metrics['distance_km'] ?? null;
        if (! is_numeric($distanceKm) || (float) $distanceKm < 0) {
            return null;
        }

        return round(((float) $distanceKm) * self::LITERS_PER_KILOMETER, 3);
    }

    public function litersPerKilometer(): float
    {
        return self::LITERS_PER_KILOMETER;
    }

    public function estimateLegacyLiters(Trip $trip): float
    {
        $distanceMeters = 0.0;
        $previous = null;
        foreach ($trip->points()
            ->orderBy('sequence_number')
            ->cursor(['latitude', 'longitude']) as $point) {
            if ($previous !== null) {
                $distanceMeters += $this->haversineMeters(
                    (float) $previous->latitude,
                    (float) $previous->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude,
                );
            }
            $previous = $point;
        }

        $idleMinutes = $trip->stops->sum(function ($stop): int {
            if (! $stop->ended_at) {
                return 0;
            }

            return (int) $stop->started_at->diffInMinutes($stop->ended_at);
        });

        return round(
            ($distanceMeters / 1000 * self::LITERS_PER_KILOMETER)
            + ($idleMinutes / 60),
            3,
        );
    }

    public function hasSufficientQualityData(array $metrics): bool
    {
        $sampleCount = (int) ($metrics['sample_count'] ?? 0);
        $qualitySampleCount = (int) ($metrics['quality_sample_count'] ?? 0);

        return $sampleCount > 0
            && ($qualitySampleCount / $sampleCount) >= self::MIN_QUALITY_DATA_COVERAGE;
    }

    public function metrics(Trip $trip): array
    {
        $points = $trip->points()
            ->orderBy('sequence_number')
            ->cursor([
                'latitude',
                'longitude',
                'accuracy',
                'speed',
                'captured_at',
                'recorded_at',
                'client_is_valid',
                'quality_reason',
            ]);

        return $this->metricsFromPoints(
            $points,
            $trip->started_at,
            $trip->ended_at,
        );
    }

    public function metricsFromPoints(
        iterable $points,
        ?DateTimeInterface $startedAt,
        ?DateTimeInterface $endedAt,
        bool $includeSegments = false,
    ): array {
        $distanceMeters = 0.0;
        $rawDistanceMeters = 0.0;
        $rejected = 0;
        $collapsed = 0;
        $rejectionCounts = [];
        $collapsedCounts = [];
        $sampleCount = 0;
        $qualitySampleCount = 0;
        $acceptedPoints = 0;
        $flags = [];
        $previousRaw = null;
        $lastEligible = null;
        $distanceAnchor = null;
        $acceptedSegments = [];
        $gapConnectors = [];
        $recordedStart = null;
        $recordedEnd = null;
        $startTimestamp = $startedAt?->getTimestamp();
        $endTimestamp = $endedAt?->getTimestamp();
        $reject = static function (string $reason) use (&$rejected, &$rejectionCounts): void {
            $rejected++;
            $rejectionCounts[$reason] = ($rejectionCounts[$reason] ?? 0) + 1;
        };
        $collapse = static function (string $reason) use (&$collapsed, &$collapsedCounts): void {
            $collapsed++;
            $collapsedCounts[$reason] = ($collapsedCounts[$reason] ?? 0) + 1;
        };
        $markCoverageGap = static function (int $seconds) use (&$flags): void {
            if ($seconds > self::MAX_SEGMENT_GAP_SECONDS) {
                $flags['tracking_gap'] = true;
            }
            if ($seconds >= self::SEVERE_COVERAGE_GAP_SECONDS) {
                $flags['tracking_coverage_gap'] = true;
            }
        };

        foreach ($points as $point) {
            $sampleCount++;
            $accuracy = is_array($point)
                ? ($point['accuracy'] ?? null)
                : ($point->accuracy ?? null);
            if (is_numeric($accuracy) && is_finite((float) $accuracy) && (float) $accuracy > 0) {
                $qualitySampleCount++;
            }
            $current = $this->point($point);
            if ($current === null) {
                $reject('invalid_point');
                $flags['invalid_point'] = true;

                continue;
            }

            if ($previousRaw !== null) {
                $rawDistanceMeters += $this->haversineMeters(
                    $previousRaw['latitude'],
                    $previousRaw['longitude'],
                    $current['latitude'],
                    $current['longitude'],
                );
            }
            $previousRaw = $current;
            $recordedStart ??= $this->coordinate($current);
            $recordedEnd = $this->coordinate($current);

            if (! $current['client_is_valid']) {
                $qualityFlag = $this->clientQualityFlag($current['quality_reason']);
                $reject($qualityFlag);
                $flags[$qualityFlag] = true;

                continue;
            }
            if ($current['recorded_timestamp'] !== null) {
                $age = $current['recorded_timestamp'] - $current['timestamp'];
                if ($age > self::MAX_POSITION_AGE_SECONDS ||
                    $age < -self::FUTURE_POSITION_TOLERANCE_SECONDS) {
                    $reject('stale_or_future_timestamp');
                    $flags['stale_or_future_timestamp'] = true;

                    continue;
                }
            }

            if ($current['accuracy'] <= 0 ||
                $current['accuracy'] > self::MAX_ACCURACY_METERS) {
                $reject('poor_accuracy');
                $flags['poor_accuracy'] = true;

                continue;
            }
            if ($current['speed'] !== null &&
                $current['speed'] > self::MAX_SPEED_METERS_PER_SECOND) {
                $reject('impossible_speed');
                $flags['impossible_speed'] = true;

                continue;
            }
            if (($startTimestamp !== null && $current['timestamp'] < $startTimestamp) ||
                ($endTimestamp !== null && $current['timestamp'] > $endTimestamp)) {
                $reject('point_outside_trip_time');
                $flags['point_outside_trip_time'] = true;

                continue;
            }

            $coverageGapSeconds = $lastEligible === null
                ? ($startTimestamp === null ? 0 : $current['timestamp'] - $startTimestamp)
                : $current['timestamp'] - $lastEligible['timestamp'];
            if ($lastEligible !== null && $coverageGapSeconds < 0) {
                $reject('out_of_order_time');
                $flags['out_of_order_time'] = true;

                continue;
            }
            if ($lastEligible !== null && $coverageGapSeconds === 0) {
                $collapse('duplicate_timestamp');

                continue;
            }

            $markCoverageGap($coverageGapSeconds);
            $acceptedPoints++;
            $lastEligible = $current;

            if ($distanceAnchor === null ||
                $coverageGapSeconds > self::MAX_SEGMENT_GAP_SECONDS) {
                $distanceAnchor = $current;
                if ($includeSegments) {
                    if ($acceptedSegments !== []) {
                        $previousSegment = $acceptedSegments[array_key_last($acceptedSegments)];
                        $previousCoordinate = $previousSegment[array_key_last($previousSegment)] ?? null;
                        if ($previousCoordinate !== null) {
                            $gapConnectors[] = [
                                'from' => $previousCoordinate,
                                'to' => $this->coordinate($current),
                                'seconds' => $coverageGapSeconds,
                            ];
                        }
                    }
                    $acceptedSegments[] = [$this->coordinate($current)];
                }

                continue;
            }

            $seconds = $current['timestamp'] - $distanceAnchor['timestamp'];
            $segmentMeters = $this->haversineMeters(
                $distanceAnchor['latitude'],
                $distanceAnchor['longitude'],
                $current['latitude'],
                $current['longitude'],
            );
            $uncertainty = $distanceAnchor['accuracy'] + $current['accuracy'];
            $noiseRadius = max(
                8.0,
                min(self::MAX_ACCURACY_METERS * 2, $uncertainty),
            );
            if ($segmentMeters <= $noiseRadius) {
                $collapse('gps_noise');
                $flags['gps_noise'] = true;

                continue;
            }
            if ($segmentMeters / $seconds > self::MAX_SPEED_METERS_PER_SECOND) {
                $reject('gps_jump');
                $flags['gps_jump'] = true;

                continue;
            }

            $distanceMeters += $segmentMeters;
            $distanceAnchor = $current;
            if ($includeSegments) {
                $acceptedSegments[array_key_last($acceptedSegments)][] =
                    $this->coordinate($current);
            }
        }

        if ($endTimestamp !== null) {
            $coverageStart = $lastEligible['timestamp'] ?? $startTimestamp;
            if ($coverageStart !== null && $endTimestamp >= $coverageStart) {
                $markCoverageGap($endTimestamp - $coverageStart);
            }
        }

        if ($sampleCount === 0) {
            $flags['no_gps_points'] = true;
        } elseif ($acceptedPoints === 0) {
            $flags['no_valid_points'] = true;
        }

        $reportedDuration = null;
        $trustedDuration = null;
        if ($startTimestamp !== null && $endTimestamp !== null) {
            $reportedDuration = (int) floor(($endTimestamp - $startTimestamp) / 60);
            if ($reportedDuration < 0) {
                $flags['invalid_duration'] = true;
                $trustedDuration = 0;
            } else {
                if ($reportedDuration > self::MAX_TRIP_MINUTES) {
                    $flags['duration_over_12h'] = true;
                }
                $trustedDuration = min($reportedDuration, self::MAX_TRIP_MINUTES);
            }
        }

        $distanceKm = round($distanceMeters / 1000, 3);
        if ($distanceKm > self::MAX_DISTANCE_KM_WITHOUT_REVIEW) {
            $flags['distance_over_policy'] = true;
        }

        $metrics = [
            'distance_km' => $distanceKm,
            'raw_distance_km' => round($rawDistanceMeters / 1000, 3),
            'duration_minutes' => $trustedDuration,
            'reported_duration_minutes' => $reportedDuration,
            'rejected_points' => $rejected,
            'collapsed_points' => $collapsed,
            'rejection_counts' => $rejectionCounts,
            'collapsed_counts' => $collapsedCounts,
            'quality_flags' => array_keys($flags),
            'sample_count' => $sampleCount,
            'quality_sample_count' => $qualitySampleCount,
        ];
        if ($includeSegments) {
            $metrics['accepted_segments'] = $acceptedSegments;
            $metrics['gap_connectors'] = $gapConnectors;
        }
        $metrics['recorded_start'] = $recordedStart;
        $metrics['recorded_end'] = $recordedEnd;

        return $metrics;
    }

    private function point(mixed $point): ?array
    {
        $value = static fn (string $key): mixed => is_array($point)
            ? ($point[$key] ?? null)
            : ($point->{$key} ?? null);

        $latitude = $value('latitude');
        $longitude = $value('longitude');
        $capturedAt = $value('captured_at');
        $recordedAt = $value('recorded_at');
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;
        if (! is_finite($lat) || ! is_finite($lng) ||
            $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ||
            ($lat === 0.0 && $lng === 0.0)) {
            return null;
        }

        $timestamp = $capturedAt instanceof DateTimeInterface
            ? $capturedAt->getTimestamp()
            : strtotime((string) $capturedAt);
        if ($timestamp === false) {
            return null;
        }
        $recordedTimestamp = $recordedAt instanceof DateTimeInterface
            ? $recordedAt->getTimestamp()
            : ($recordedAt === null || $recordedAt === ''
                ? null
                : strtotime((string) $recordedAt));
        if ($recordedTimestamp === false) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => is_numeric($value('accuracy'))
                ? (float) $value('accuracy')
                : 0.0,
            'speed' => is_numeric($value('speed')) ? (float) $value('speed') : null,
            'timestamp' => $timestamp,
            'recorded_timestamp' => $recordedTimestamp,
            'client_is_valid' => $value('client_is_valid') === null
                ? true
                : filter_var($value('client_is_valid'), FILTER_VALIDATE_BOOL),
            'quality_reason' => is_string($value('quality_reason'))
                ? $value('quality_reason')
                : null,
        ];
    }

    private function clientQualityFlag(?string $reason): string
    {
        return match ($reason) {
            'invalid_coordinates' => 'invalid_point',
            'mocked_location' => 'mocked_location',
            'poor_accuracy' => 'poor_accuracy',
            'impossible_reported_speed' => 'impossible_speed',
            'stale_or_future_timestamp' => 'stale_or_future_timestamp',
            'out_of_order_timestamp' => 'out_of_order_time',
            'impossible_derived_speed' => 'gps_jump',
            'within_gps_noise' => 'gps_noise',
            default => 'client_rejected',
        };
    }

    private function coordinate(array $point): array
    {
        return [
            'lat' => $point['latitude'],
            'lng' => $point['longitude'],
        ];
    }

    private function haversineMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $earthMeters = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $a = min(1.0, max(0.0, $a));

        return $earthMeters * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
