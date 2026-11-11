<?php
declare(strict_types=1);
namespace Luvittor\GeoIpUpdater\Tests;

use Luvittor\GeoIpUpdater\NativeHttpClient;
use Luvittor\GeoIpUpdater\Exception\UpdateException;
use PHPUnit\Framework\TestCase;

final class NativeHttpClientTest extends TestCase
{
    private array $servers=[]; private int $port; private int $otherPort;
    protected function setUp(): void
    {
        $this->port = random_int(20000, 30000); $this->otherPort=$this->port+10000; $router=__DIR__.'/fixtures/redirect-router.php'; putenv('CROSS_TARGET=http://127.0.0.1:'.$this->otherPort.'/capture');
        $this->servers[]=proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$this->port, $router], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $this->servers[]=proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$this->otherPort, $router], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        usleep(200000);
    }
    protected function tearDown(): void { foreach($this->servers as $server) if(is_resource($server)) proc_terminate($server); putenv('CROSS_TARGET'); }
    public function testItUsesFinalStatusAndHeadersAfterRedirects(): void
    {
        $r=(new NativeHttpClient())->request('GET','http://127.0.0.1:'.$this->port.'/many',['Authorization: Basic secret'],3);
        self::assertSame(200,$r->status); self::assertSame('final',$r->headers['etag']); self::assertSame('done',$r->body); self::assertSame(2,$r->redirects);
    }
    public function testItDoesNotForwardAuthorizationAcrossOrigins(): void
    {
        $r=(new NativeHttpClient())->request('GET','http://127.0.0.1:'.$this->port.'/cross',['Authorization: Basic secret'],3);
        self::assertSame(200,$r->status); self::assertSame('none',$r->body);
    }
    public function testItEnforcesRedirectLimit(): void
    { $this->expectException(UpdateException::class); $this->expectExceptionMessage('Too many'); (new NativeHttpClient())->request('GET','http://127.0.0.1:'.$this->port.'/loop',[],3); }
    public function testItBlocksHttpsDowngrade(): void
    { $method=(new \ReflectionClass(NativeHttpClient::class))->getMethod('isHttpsDowngrade'); self::assertTrue($method->invoke(new NativeHttpClient(),'https://maxmind.test/a','http://maxmind.test/a')); self::assertFalse($method->invoke(new NativeHttpClient(),'https://maxmind.test/a','https://other.test/a')); }
}
