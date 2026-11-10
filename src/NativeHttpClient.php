<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Contract\HttpClient;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

final class NativeHttpClient implements HttpClient
{
    public function request(string $method, string $url, array $headers, int $timeout): HttpResponse
    {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $context); $lines = $http_response_header ?? [];
        if ($body === false && $lines === []) throw new UpdateException('Network request failed.');
        $status = (int) preg_replace('/^HTTP\/\S+\s+(\d+).*$/', '$1', $lines[0] ?? '0'); $parsed=[];
        foreach ($lines as $line) if (str_contains($line, ':')) { [$name,$value] = explode(':', $line, 2); $parsed[strtolower($name)] = trim($value); }
        return new HttpResponse($status, $parsed, $body === false ? '' : $body);
    }
}
