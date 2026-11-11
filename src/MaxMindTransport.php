<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Contract\Transport;
use Luvittor\GeoIpUpdater\Contract\HttpClient;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

/** Native PHP HTTP transport; credentials never occur in exception text. */
final class MaxMindTransport implements Transport
{
    public function __construct(private string $accountId, private string $licenseKey, private string $baseUrl = 'https://download.maxmind.com/geoip/databases', private int $timeout = 120, private int $attempts = 3, private int $backoffMilliseconds = 1000, ?HttpClient $client = null)
    { if ($accountId === '' || $licenseKey === '') throw new \InvalidArgumentException('MaxMind credentials are required.'); $this->client = $client ?? new NativeHttpClient(); }
    private HttpClient $client;
    public function metadata(string $product): RemoteDatabase
    {
        $url = rtrim($this->baseUrl, '/').'/'.rawurlencode($product).'/download?suffix=tar.gz';
        $response = $this->send('HEAD', $url, 'metadata');
        $version = $response->headers['etag'] ?? ($response->headers['last-modified'] ?? 'unknown');
        return new RemoteDatabase(trim($version, "\""), $url);
    }
    public function download(RemoteDatabase $database, string $destination): void
    {
        $response = $this->send('GET', $database->url, 'download');
        if (file_put_contents($destination, $response->body, LOCK_EX) !== false && filesize($destination) > 0) return;
        throw new UpdateException('Database download was empty.', 0, null, 'empty_download', 'download');
    }
    private function send(string $method, string $url, string $phase): HttpResponse
    {
        $headers = ['Authorization: Basic '.base64_encode($this->accountId.':'.$this->licenseKey), 'User-Agent: luvittor/geoip-updater'];
        for ($attempt=1; $attempt<=max(1,$this->attempts); $attempt++) {
            try { $response = $this->client->request($method, $url, $headers, $this->timeout); }
            catch (\Throwable $e) { if ($attempt === $this->attempts) throw new UpdateException('Temporary database service failure.', 0, $e, 'network_failure', $phase, null, $attempt); $this->backoff($attempt); continue; }
            if ($response->status >= 200 && $response->status < 300) return $response;
            if ($response->status >= 400 && $response->status < 500 && $response->status !== 429) { $code=$response->status === 401 ? 'authentication_failed' : ($response->status === 403 ? 'authorization_failed' : 'request_rejected'); throw new UpdateException('MaxMind rejected the request (HTTP '.$response->status.').', 0, null, $code, $phase, $response->status, $attempt); }
            if ($attempt < $this->attempts) { $this->backoff($attempt); continue; }
            throw new UpdateException('Temporary database service failure.', 0, null, 'temporary_http_failure', $phase, $response->status, $attempt);
        }
        throw new UpdateException('Temporary database service failure.', 0, null, 'temporary_http_failure', $phase);
    }
    private function backoff(int $attempt): void { if ($this->backoffMilliseconds > 0) usleep($this->backoffMilliseconds * 1000 * $attempt); }
}
