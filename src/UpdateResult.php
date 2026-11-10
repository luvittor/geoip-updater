<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class UpdateResult
{
    public function __construct(public string $edition, public string $status, public string $path, public ?string $version = null, public ?string $reason = null) {}
    public static function failed(Edition $edition, string $reason): self { return new self($edition->name, 'failed', $edition->targetPath, null, $reason); }
}
