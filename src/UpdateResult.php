<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

final readonly class UpdateResult
{
    public function __construct(public string $edition, public string $status, public string $path, public ?string $version = null, public ?string $reason = null, public ?string $errorCode = null, public ?string $phase = null, public ?int $httpStatus = null, public ?int $attempts = null) {}
    public static function failed(Edition $edition, string $reason, ?string $errorCode = null, ?string $phase = null, ?int $httpStatus = null, ?int $attempts = null): self
    { return new self($edition->name, 'failed', $edition->targetPath, null, $reason, $errorCode, $phase, $httpStatus, $attempts); }
}
