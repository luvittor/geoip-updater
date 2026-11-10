<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class Edition
{
    public function __construct(public string $name, public string $databaseType, public string $targetPath)
    {
        if ($name === '' || $databaseType === '' || $targetPath === '') throw new \InvalidArgumentException('Edition fields cannot be empty.');
    }
    public function product(): string { return 'GeoLite2-'.$this->name; }
}
