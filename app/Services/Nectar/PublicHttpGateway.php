<?php

namespace App\Services\Nectar;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class PublicHttpGateway
{
    public function publicAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $bytes = inet_pton($ip);
        if (strlen($bytes) === 16) {
            // Restrict IPv6 to global unicast, excluding transition and documentation networks.
            return (ord($bytes[0]) & 0xE0) === 0x20
                && ! str_starts_with(strtolower($ip), '2001:')
                && ! str_starts_with(strtolower($ip), '2002:');
        }
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);
        $third = ord($bytes[2]);

        return $first !== 0 && $first < 224
            && ! ($first === 100 && $second >= 64 && $second <= 127)
            && ! ($first === 198 && ($second === 18 || $second === 19 || ($second === 51 && $third === 100)))
            && ! ($first === 192 && ($second === 0 || ($second === 88 && $third === 99)))
            && ! ($first === 203 && $second === 0 && $third === 113);
    }

    public function destination(string $url, array $hosts): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || ! in_array($host, $hosts, true)) {
            throw ValidationException::withMessages(['url' => __('Use HTTPS on port 443 with an explicitly approved hostname.')]);
        }

        return $host;
    }

    protected function addresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_unique(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: []))));
    }

    public function request(array $input, array $hosts): array
    {
        if (strlen($input['body'] ?? '') > 16384) {
            throw ValidationException::withMessages(['body' => __('Request bodies are limited to 16384 bytes.')]);
        }
        $host = $this->destination($input['url'], $hosts);
        $addresses = $this->addresses($host);
        if (! $addresses || count(array_filter($addresses, fn ($ip) => ! $this->publicAddress($ip)))) {
            throw ValidationException::withMessages(['url' => __('The hostname must resolve exclusively to public addresses.')]);
        }
        if (! function_exists('curl_init')) {
            throw new RuntimeException('The HTTP gateway requires the PHP cURL extension.');
        }
        $ip = $addresses[0];
        $body = '';
        $tooLarge = false;
        $headerBytes = 0;
        $curl = curl_init($input['url']);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $input['method'] ?? 'GET',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => ["{$host}:443:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)],
            CURLOPT_HEADERFUNCTION => function ($curl, string $chunk) use (&$headerBytes): int {
                $headerBytes += strlen($chunk);

                return $headerBytes <= 32768 ? strlen($chunk) : 0;
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > 1048576) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        if (($input['method'] ?? 'GET') === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $input['body'] ?? '');
        }
        try {
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($tooLarge) {
                throw new RuntimeException('The remote response exceeded the size limit.');
            }
            if ($ok === false) {
                throw new RuntimeException('The remote HTTPS request failed.');
            }
            // Redirects are rejected rather than resolving another unapproved destination.
            if ($status >= 300 && $status < 400) {
                throw new RuntimeException('Redirects are not permitted by this gateway.');
            }

            return ['status' => $status, 'body_base64' => base64_encode($body)];
        } finally {
            curl_close($curl);
        }
    }
}
