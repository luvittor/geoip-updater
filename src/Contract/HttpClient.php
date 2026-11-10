<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater\Contract;

use Luvittor\GeoIpUpdater\HttpResponse;

interface HttpClient
{
    /** @param list<string> $headers */
    public function request(string $method, string $url, array $headers, int $timeout): HttpResponse;
}
