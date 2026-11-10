<?php
declare(strict_types=1);
namespace Luvittor\GeoIpUpdater\Tests;

use Luvittor\GeoIpUpdater\{Edition, RemoteDatabase, UpdateOptions, Updater};
use Luvittor\GeoIpUpdater\Contract\{DatabaseValidator, Transport};
use PHPUnit\Framework\TestCase;

final class UpdaterTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'geoip-updater-'.bin2hex(random_bytes(5)); mkdir($this->root); }
    protected function tearDown(): void { $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($this->root); }
    public function testItRejectsRelativeDirectories(): void { $this->expectExceptionMessage('absolute'); $this->updater()->update(new UpdateOptions('relative',[new Edition('City','City','relative/a.mmdb')])); }
    public function testItReportsCurrentOnlyAfterValidation(): void { $e=$this->edition('City'); file_put_contents($e->targetPath,'old'); file_put_contents($e->targetPath.'.version',"v1\n"); $transport=$this->transport('v1'); $result=(new Updater($transport,$this->validator()))->update(new UpdateOptions($this->root,[$e])); self::assertSame('updated',$result[0]->status); }
    public function testPreparationFailurePreservesExistingFiles(): void { $e=$this->edition('City'); file_put_contents($e->targetPath,'old'); $transport=$this->transport('v2', true); $result=(new Updater($transport,$this->validator()))->update(new UpdateOptions($this->root,[$e])); self::assertSame('failed',$result[0]->status); self::assertSame('old',file_get_contents($e->targetPath)); }
    public function testItRejectsAnOverlappingUpdate(): void { $handle=fopen($this->root.DIRECTORY_SEPARATOR.'.geoipupdate.lock','c'); flock($handle,LOCK_EX); try { $this->expectExceptionMessage('Another GeoIP update'); $this->updater()->update(new UpdateOptions($this->root,[$this->edition('City')],30,0)); } finally { flock($handle,LOCK_UN); fclose($handle); } }
    private function edition(string $name): Edition { return new Edition($name,$name,$this->root.DIRECTORY_SEPARATOR.'GeoLite2-'.$name.'.mmdb'); }
    private function updater(): Updater { return new Updater($this->transport('v1'),$this->validator()); }
    private function validator(): DatabaseValidator { return new class implements DatabaseValidator { public function validate(string $path,string $type): array { if (!is_file($path) || file_get_contents($path)==='old') throw new \RuntimeException('invalid'); return ['type'=>$type,'builtAt'=>new \DateTimeImmutable('now')]; } }; }
    private function transport(string $version, bool $bad=false): Transport { return new class($version,$bad) implements Transport { public function __construct(private string $v,private bool $bad) {} public function metadata(string $p): RemoteDatabase{return new RemoteDatabase($this->v,'fake://archive');} public function download(RemoteDatabase $d,string $out):void { if($this->bad){file_put_contents($out,'invalid');return;} $tar=$out.'.tmp.tar'; $p=new \PharData($tar); $p->addFromString('GeoLite2-City/GeoLite2-City.mmdb','new'); $p->compress(\Phar::GZ); rename($tar.'.gz',$out); unlink($tar); } }; }
}
