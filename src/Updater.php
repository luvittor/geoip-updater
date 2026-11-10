<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Contract\DatabaseValidator;
use Luvittor\GeoIpUpdater\Contract\Transport;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

final class Updater
{
    public function __construct(private Transport $transport, private DatabaseValidator $validator, private ?SafeTarExtractor $extractor = null) { $this->extractor ??= new SafeTarExtractor(); }
    /** @return list<UpdateResult> */
    public function update(UpdateOptions $options): array
    {
        $this->prepareDirectory($options); $lock = $this->lock($options); $stage = $options->directory.DIRECTORY_SEPARATOR.'.geoip-stage-'.bin2hex(random_bytes(8));
        try {
            if (!mkdir($stage, $options->directoryMode, true)) throw new UpdateException('Cannot create staging directory.');
            $prepared=[]; $results=[];
            foreach ($options->editions as $edition) {
                try {
                    $remote = $this->transport->metadata($edition->product());
                    if ($this->isCurrent($edition, $remote, $options)) { $results[] = new UpdateResult($edition->name, 'current', $edition->targetPath, $remote->version); continue; }
                    $archive = $stage.DIRECTORY_SEPARATOR.$edition->name.'.tar.gz'; $this->transport->download($remote, $archive);
                    $extract = $stage.DIRECTORY_SEPARATOR.$edition->name; $this->extractor->extract($archive, $extract);
                    $candidate = $stage.DIRECTORY_SEPARATOR.basename($edition->targetPath); $source=$this->extractor->findDatabase($extract, basename($edition->targetPath));
                    if (!copy($source,$candidate)) throw new UpdateException('Cannot stage database.');
                    $this->assertValid($candidate,$edition,$options); $prepared[] = [$edition,$candidate,$remote->version];
                } catch (\Throwable $e) { return [...$results, UpdateResult::failed($edition, $this->safeReason($e))]; }
            }
            $this->install($prepared); foreach ($prepared as [$edition,, $version]) $results[] = new UpdateResult($edition->name, 'updated', $edition->targetPath, $version); return $results;
        } finally { $this->remove($stage); flock($lock, LOCK_UN); fclose($lock); }
    }
    private function isCurrent(Edition $edition, RemoteDatabase $remote, UpdateOptions $options): bool
    { try { $this->assertValid($edition->targetPath,$edition,$options); $v=@file_get_contents($this->versionPath($edition)); return is_string($v) && trim($v) === $remote->version; } catch (\Throwable) { return false; } }
    private function assertValid(string $path, Edition $edition, UpdateOptions $options): void
    { $metadata=$this->validator->validate($path,$edition->databaseType); if ($metadata['builtAt'] < (new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('-'.$options->maxAgeDays.' days')) throw new UpdateException('Database exceeds maximum age.'); }
    /** @param list<array{0:Edition,1:string,2:string}> $prepared */
    private function install(array $prepared): void
    { $backups=[]; $installed=[]; try { foreach ($prepared as [$edition,$source,$version]) { $target=$edition->targetPath; $new=$target.'.new-'.bin2hex(random_bytes(6)); $backup=$target.'.previous-'.bin2hex(random_bytes(6)); if (!copy($source,$new)) throw new UpdateException('Cannot install database.'); if (is_file($target) && !rename($target,$backup)) throw new UpdateException('Cannot preserve current database.'); $backups[$target]=is_file($backup)?$backup:null; $vp=$this->versionPath($edition); $vbackup=$vp.'.previous-'.bin2hex(random_bytes(6)); if (is_file($vp) && !rename($vp,$vbackup)) throw new UpdateException('Cannot preserve database metadata.'); $backups[$vp]=is_file($vbackup)?$vbackup:null; if (!rename($new,$target)) throw new UpdateException('Cannot replace database.'); if (file_put_contents($vp,$version."\n",LOCK_EX)===false) throw new UpdateException('Cannot save database metadata.'); $installed[]=$target; $installed[]=$vp; } }
        catch (\Throwable $e) { foreach ($installed as $path) @unlink($path); foreach ($backups as $target=>$backup) if ($backup !== null) @rename($backup,$target); throw $e instanceof UpdateException ? $e : new UpdateException('Installation failed.',0,$e); }
        finally { foreach ($backups as $backup) if ($backup !== null) @unlink($backup); } }
    private function prepareDirectory(UpdateOptions $o): void
    { if (!$this->absolute($o->directory)) throw new UpdateException('Database directory must be absolute.'); if (file_exists($o->directory)&&!is_dir($o->directory)) throw new UpdateException('Database path is not a directory.'); if (!is_dir($o->directory)&&!mkdir($o->directory,$o->directoryMode,true)&&!is_dir($o->directory)) throw new UpdateException('Cannot create database directory.'); if (!is_writable($o->directory)) throw new UpdateException('Database directory is not writable.'); foreach($o->editions as $e) if (dirname($e->targetPath)!==$o->directory) throw new UpdateException('Database targets must be direct children of the database directory.'); }
    private function lock(UpdateOptions $o): mixed { $h=@fopen($o->directory.DIRECTORY_SEPARATOR.'.geoipupdate.lock','c'); if($h===false) throw new UpdateException('Cannot open update lock.'); $until=microtime(true)+$o->lockTimeoutSeconds; do { if(flock($h,LOCK_EX|LOCK_NB)) return $h; usleep(100000); } while(microtime(true)<$until); fclose($h); throw new UpdateException('Another GeoIP update is in progress.'); }
    private function absolute(string $p): bool { return str_starts_with($p,'/') || preg_match('#^[A-Za-z]:[\\\\/]#',$p)===1 || str_starts_with($p,'\\\\'); }
    private function versionPath(Edition $e): string { return $e->targetPath.'.version'; }
    private function safeReason(\Throwable $e): string { return $e instanceof UpdateException ? $e->getMessage() : 'Update failed.'; }
    private function remove(string $dir): void { if(!is_dir($dir)) return; $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname()); @rmdir($dir); }
}
