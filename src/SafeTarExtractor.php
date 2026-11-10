<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Exception\UpdateException;

final class SafeTarExtractor
{
    public function extract(string $archive, string $destination): void
    {
        try {
            $tar = str_ends_with($archive, '.gz') ? substr($archive, 0, -3) : $archive;
            if ($tar !== $archive) (new \PharData($archive))->decompress();
            $phar = new \PharData($tar);
            foreach (new \RecursiveIteratorIterator($phar) as $entry) {
                $name = str_replace('\\', '/', $entry->getPathName());
                if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || $entry->isLink()) throw new UpdateException('Archive contains an unsafe entry.');
            }
            if (!mkdir($destination, 0750, true) && !is_dir($destination)) throw new UpdateException('Cannot create extraction directory.');
            $phar->extractTo($destination, null, true);
        } catch (UpdateException $e) { throw $e; }
        catch (\Throwable $e) { throw new UpdateException('Archive extraction failed.', 0, $e); }
    }
    public function findDatabase(string $directory, string $filename): string
    {
        $root = realpath($directory); if ($root === false) throw new UpdateException('Extraction directory is unavailable.');
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || $file->isLink() || $file->getFilename() !== $filename) continue;
            $path = realpath($file->getPathname()); if ($path !== false && str_starts_with($path, $root.DIRECTORY_SEPARATOR)) return $path;
        }
        throw new UpdateException('Archive does not contain the expected database.');
    }
}
