<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater\Exception;

final class UpdateException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly string $errorCode = 'update_failed',
        public readonly string $phase = 'update',
        public readonly ?int $httpStatus = null,
        public readonly ?int $attempts = null,
    ) { parent::__construct($message, $code, $previous); }

    /** @return array{error_code:string,phase:string,http_status:?int,attempts:?int} */
    public function diagnostic(): array
    { return ['error_code' => $this->errorCode, 'phase' => $this->phase, 'http_status' => $this->httpStatus, 'attempts' => $this->attempts]; }
}
