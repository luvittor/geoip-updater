<?php
declare(strict_types=1);

namespace Luvittor\GeoIpUpdater;

use Luvittor\GeoIpUpdater\Contract\HttpClient;
use Luvittor\GeoIpUpdater\Exception\UpdateException;

final class NativeHttpClient implements HttpClient
{
    public function request(string $method, string $url, array $headers, int $timeout): HttpResponse
    {
        $current = $url; $requestHeaders = $headers; $redirects = 0;
        while (true) {
            $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $requestHeaders), 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0]]);
            $body = @file_get_contents($current, false, $context); $lines = $http_response_header ?? [];
            if ($body === false && $lines === []) throw new UpdateException('Network request failed.', 0, null, 'network_failure', 'http');
            $status = (int) preg_replace('/^HTTP\/\S+\s+(\d+).*$/', '$1', $lines[0] ?? '0'); $parsed=[];
            foreach ($lines as $line) if (str_contains($line, ':')) { [$name,$value] = explode(':', $line, 2); $parsed[strtolower($name)] = trim($value); }
            if (!in_array($status, [301,302,303,307,308], true) || !isset($parsed['location'])) return new HttpResponse($status, $parsed, $body === false ? '' : $body, $current, $redirects);
            if (++$redirects > 5) throw new UpdateException('Too many HTTP redirects.', 0, null, 'redirect_limit_exceeded', 'http', $status);
            $next = $this->resolveUrl($current, $parsed['location']);
            if ($next === null) throw new UpdateException('Unsafe HTTP redirect.', 0, null, 'unsafe_redirect', 'http', $status);
            if ($this->isHttpsDowngrade($current, $next)) throw new UpdateException('Insecure HTTP redirect blocked.', 0, null, 'insecure_redirect_downgrade', 'http', $status);
            if ($this->origin($next) !== $this->origin($current)) $requestHeaders = array_values(array_filter($requestHeaders, static fn(string $header): bool => !str_starts_with(strtolower($header), 'authorization:')));
            $current = $next;
        }
    }
    private function origin(string $url): string { $p=parse_url($url); return strtolower(($p['scheme'] ?? '').'://'.($p['host'] ?? '').':'.($p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80))); }
    private function isHttpsDowngrade(string $from, string $to): bool { return strtolower((string)(parse_url($from, PHP_URL_SCHEME) ?? '')) === 'https' && strtolower((string)(parse_url($to, PHP_URL_SCHEME) ?? '')) === 'http'; }
    private function resolveUrl(string $base, string $location): ?string
    { if (preg_match('#^https?://#i', $location)) $url=$location; elseif (str_starts_with($location, '//')) { $p=parse_url($base); $url=($p['scheme'] ?? 'https').':'.$location; } else { $p=parse_url($base); if (!$p || !isset($p['scheme'],$p['host'])) return null; $path=str_starts_with($location,'/') ? $location : rtrim(dirname($p['path'] ?? '/'),'/').'/'.$location; $url=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'').$path; } $p=parse_url($url); return isset($p['scheme'],$p['host']) && in_array(strtolower($p['scheme']),['http','https'],true) ? $url : null; }
}
