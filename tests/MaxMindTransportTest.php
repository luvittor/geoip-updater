<?php
declare(strict_types=1);
namespace Luvittor\GeoIpUpdater\Tests;

use Luvittor\GeoIpUpdater\Contract\HttpClient;
use Luvittor\GeoIpUpdater\Exception\UpdateException;
use Luvittor\GeoIpUpdater\HttpResponse;
use Luvittor\GeoIpUpdater\MaxMindTransport;
use Luvittor\GeoIpUpdater\RemoteDatabase;
use PHPUnit\Framework\TestCase;

final class MaxMindTransportTest extends TestCase
{
    public function testItAuthenticatesAndUsesMetadataVersion(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, ['etag' => '"20261110"'])]);
        $transport = new MaxMindTransport('account', 'secret', 'https://maxmind.test/db', 3, 1, 0, $client);
        self::assertSame('20261110', $transport->metadata('GeoLite2-City')->version);
        self::assertSame('HEAD', $client->requests[0][0]);
        self::assertContains('Authorization: Basic '.base64_encode('account:secret'), $client->requests[0][2]);
    }
    public function testItRetries429And5xx(): void
    {
        $client = new FakeHttpClient([new HttpResponse(429, []), new HttpResponse(503, []), new HttpResponse(200, ['etag' => 'v2'])]);
        $transport = new MaxMindTransport('account', 'secret', 'https://maxmind.test', 3, 3, 0, $client);
        self::assertSame('v2', $transport->metadata('GeoLite2-City')->version);
        self::assertCount(3, $client->requests);
    }
    public function testItSanitizesPermanentAuthenticationFailure(): void
    {
        $client = new FakeHttpClient([new HttpResponse(401, [])]);
        $transport = new MaxMindTransport('account', 'very-secret', 'https://maxmind.test', 3, 1, 0, $client);
        try { $transport->metadata('GeoLite2-City'); self::fail('Expected failure.'); }
        catch (UpdateException $e) { self::assertStringContainsString('HTTP 401', $e->getMessage()); self::assertStringNotContainsString('very-secret', $e->getMessage()); self::assertSame('authentication_failed', $e->errorCode); self::assertSame('metadata', $e->phase); self::assertSame(401, $e->httpStatus); self::assertSame(1, $e->attempts); }
    }
    public function testItDistinguishesForbiddenFromAuthenticationFailure(): void
    {
        $transport=new MaxMindTransport('account','secret','https://maxmind.test',3,1,0,new FakeHttpClient([new HttpResponse(403, [])]));
        try { $transport->metadata('GeoLite2-City'); self::fail('Expected failure.'); } catch(UpdateException $e) { self::assertSame('authorization_failed',$e->errorCode); self::assertSame(403,$e->httpStatus); self::assertStringNotContainsString('secret',$e->getMessage()); }
    }
    public function testItWritesADownloadedArchive(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, [], 'archive')]); $file = tempnam(sys_get_temp_dir(), 'geoip-');
        (new MaxMindTransport('a', 'b', 'https://maxmind.test', 3, 1, 0, $client))->download(new RemoteDatabase('v1', 'https://maxmind.test/file'), $file);
        self::assertSame('archive', file_get_contents($file)); unlink($file);
    }
    public function testItProvidesStructuredDiagnosticsForTemporaryAndConnectionFailures(): void
    {
        $client=new FakeHttpClient([new HttpResponse(503, []), new HttpResponse(503, [])]); $t=new MaxMindTransport('a','secret','https://maxmind.test',3,2,0,$client);
        try { $t->metadata('GeoLite2-City'); self::fail('Expected failure.'); } catch(UpdateException $e) { self::assertSame(['error_code'=>'temporary_http_failure','phase'=>'metadata','http_status'=>503,'attempts'=>2],$e->diagnostic()); self::assertStringNotContainsString('secret',$e->getMessage()); }
        $client=new FakeHttpClient([new \RuntimeException('timeout token-secret')]); $t=new MaxMindTransport('a','secret','https://maxmind.test',3,1,0,$client);
        try { $t->metadata('GeoLite2-City'); self::fail('Expected failure.'); } catch(UpdateException $e) { self::assertSame('network_failure',$e->errorCode); self::assertSame('metadata',$e->phase); self::assertSame(1,$e->attempts); self::assertStringNotContainsString('token-secret',$e->getMessage()); }
    }
}
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse|\Throwable> */ private array $responses;
    /** @var list<array{string,string,list<string>,int}> */ public array $requests=[];
    /** @param list<HttpResponse|\Throwable> $responses */ public function __construct(array $responses) { $this->responses=$responses; }
    public function request(string $method, string $url, array $headers, int $timeout): HttpResponse { $this->requests[]=[$method,$url,$headers,$timeout]; $next=array_shift($this->responses); if($next instanceof \Throwable) throw $next; return $next; }
}
