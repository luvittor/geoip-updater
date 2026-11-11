<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class HttpResponse
{
    /** @param array<string,string> $headers */
    public function __construct(public int $status, public array $headers, public string $body = '', public ?string $effectiveUrl = null, public int $redirects = 0) {}
}
