<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Contract\Transport;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

/** Native PHP HTTP transport; credentials never occur in exception text. */
final class MaxMindTransport implements Transport
{
    public function __construct(private string $accountId, private string $licenseKey, private string $baseUrl = 'https://download.maxmind.com/geoip/databases', private int $timeout = 120, private int $attempts = 3, private int $backoffMilliseconds = 1000)
    { if ($accountId === '' || $licenseKey === '') throw new \InvalidArgumentException('MaxMind credentials are required.'); }
    public function metadata(string $product): RemoteDatabase
    {
        $url = rtrim($this->baseUrl, '/').'/'.rawurlencode($product).'/download?suffix=tar.gz';
        $headers = $this->request($url, true);
        $version = $headers['etag'] ?? ($headers['last-modified'] ?? 'unknown');
        return new RemoteDatabase(trim($version, "\""), $url);
    }
    public function download(RemoteDatabase $database, string $destination): void
    {
        for ($i=1; $i <= max(1,$this->attempts); $i++) {
            $body = $this->request($database->url, false);
            if (is_string($body) && file_put_contents($destination, $body, LOCK_EX) !== false && filesize($destination) > 0) return;
            if ($i < $this->attempts) usleep(max(0,$this->backoffMilliseconds) * 1000 * $i);
        }
        throw new UpdateException('Database download failed.');
    }
    /** @return array<string,string>|string */
    private function request(string $url, bool $head): array|string
    {
        $headers = ['Authorization: Basic '.base64_encode($this->accountId.':'.$this->licenseKey), 'User-Agent: luvittor/geoip-updater'];
        $context = stream_context_create(['http' => ['method' => $head ? 'HEAD' : 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $this->timeout, 'ignore_errors' => true, 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $context); $response = $http_response_header ?? [];
        $status = (int)preg_replace('/^HTTP\/\S+\s+(\d+).*$/', '$1', $response[0] ?? '0');
        if ($status >= 400 && $status < 500 && $status !== 429) throw new UpdateException('MaxMind rejected the request (HTTP '.$status.').');
        if ($status === 429 || $status >= 500 || $body === false) throw new UpdateException('Temporary database service failure.');
        if (!$head) return $body;
        $out=[]; foreach ($response as $line) if (str_contains($line, ':')) { [$k,$v]=explode(':',$line,2); $out[strtolower($k)]=trim($v); } return $out;
    }
}
