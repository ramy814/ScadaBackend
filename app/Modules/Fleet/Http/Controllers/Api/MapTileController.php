<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Services\GeoServerTileService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** GET /api/map/tiles/{z}/{x}/{y}.png — ported from the legacy Api\MapTileController. */
class MapTileController extends Controller
{
    public function __invoke(int $zoom, int $x, int $y, GeoServerTileService $tiles): Response
    {
        try {
            $tile = $tiles->fetch($zoom, $x, $y);
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (RuntimeException) {
            abort(502, 'Map tile is temporarily unavailable.');
        }

        return response($tile->body(), 200, [
            'Content-Type' => $tile->header('Content-Type', 'image/png'),
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }
}
