<?php
declare(strict_types=1);
namespace Luvittor\GeoIpUpdater\Tests;

use Luvittor\GeoIpUpdater\Exception\UpdateException;
use Luvittor\GeoIpUpdater\MaxMindDatabaseValidator;
use PHPUnit\Framework\TestCase;

final class MaxMindDatabaseValidatorTest extends TestCase
{
    private const FIXTURE = __DIR__.'/fixtures/GeoIP2-City-Test.mmdb';
    public function testItReadsARealMmdbFixture(): void
    {
        $metadata = (new MaxMindDatabaseValidator())->validate(self::FIXTURE, 'City');
        self::assertStringContainsString('City', $metadata['type']);
        self::assertInstanceOf(\DateTimeImmutable::class, $metadata['builtAt']);
    }
    public function testItRejectsAnIncompatibleEdition(): void
    {
        $this->expectException(UpdateException::class);
        (new MaxMindDatabaseValidator())->validate(self::FIXTURE, 'ASN');
    }
    public function testItRejectsInvalidBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'geoip-invalid-'); file_put_contents($path, 'not an mmdb');
        try { $this->expectException(UpdateException::class); (new MaxMindDatabaseValidator())->validate($path, 'City'); }
        finally { unlink($path); }
    }
}
