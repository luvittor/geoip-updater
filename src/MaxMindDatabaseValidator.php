<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use GeoIp2\Database\Reader;
use Luvittor\GeoIpUpdater\Contract\DatabaseValidator;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

final class MaxMindDatabaseValidator implements DatabaseValidator
{
    public function validate(string $path, string $expectedType): array
    {
        try { $reader = new Reader($path); $meta = $reader->metadata(); $reader->close(); }
        catch (\Throwable $e) { throw new UpdateException('Database validation failed.', 0, $e); }
        $type = (string) $meta->databaseType;
        if (!str_contains(strtolower($type), strtolower($expectedType))) throw new UpdateException('Database edition is incompatible.');
        return ['type' => $type, 'builtAt' => (new \DateTimeImmutable('@'.$meta->buildEpoch))->setTimezone(new \DateTimeZone('UTC'))];
    }
}
