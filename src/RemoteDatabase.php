<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class RemoteDatabase
{
    public function __construct(public string $version, public string $url) {}
}
