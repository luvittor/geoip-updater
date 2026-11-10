<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater\Contract;

interface DatabaseValidator { /** @return array{type:string,builtAt:\DateTimeImmutable} */ public function validate(string $path, string $expectedType): array; }
