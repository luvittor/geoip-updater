<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class UpdateOptions
{
    /** @param list<Edition> $editions */
    public function __construct(
        public string $directory,
        public array $editions,
        public int $maxAgeDays = 30,
        public int $lockTimeoutSeconds = 30,
        public int $directoryMode = 0750,
    ) {}
}
