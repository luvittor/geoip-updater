<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater\Contract;

use Luvittor\GeoIpUpdater\RemoteDatabase;

interface Transport
{
    public function metadata(string $product): RemoteDatabase;
    public function download(RemoteDatabase $database, string $destination): void;
}
