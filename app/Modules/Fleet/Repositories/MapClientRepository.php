<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Repositories;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MapClientRepository
{
    public function checkToken(string $url, string $token): Response
    {
        return Http::withToken($token)
            ->acceptJson()
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(8)
            ->get($url);
    }
}
