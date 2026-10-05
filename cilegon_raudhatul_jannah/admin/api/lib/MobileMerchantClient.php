<?php

declare(strict_types=1);

final class MobileMerchantClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function create(): self
    {
        return new self(api_config());
    }

    /** @return mixed */
    public function studentRequest()
    {
        return $this->call(['METHOD' => 'StudentRequest']);
    }

    /** @return mixed */
    public function unitRequest()
    {
        return $this->call(['METHOD' => 'UnitRequest']);
    }

    /**
     * @param int|string $kodeUnit
     * @return mixed
     */
    public function studentRequestUnit($kodeUnit)
    {
        $kode = is_numeric($kodeUnit) ? (int) $kodeUnit : trim((string) $kodeUnit);
        if ($kode === '' || $kode === 0) {
            throw new InvalidArgumentException('KODEUNIT wajib diisi');
        }

        return $this->call([
            'METHOD' => 'StudentRequestUnit',
            'KODEUNIT' => $kode,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    public function call(array $payload)
    {
        $method = (string) ($payload['METHOD'] ?? '');
        $allowed = $this->config['allowed_methods'] ?? [];
        if ($method === '' || (is_array($allowed) && $allowed && !in_array($method, $allowed, true))) {
            throw new InvalidArgumentException('METHOD tidak diizinkan');
        }

        $secret = (string) ($this->config['jwt_secret'] ?? '');
        $apiUrl = (string) ($this->config['api_url'] ?? '');
        if ($secret === '' || $apiUrl === '') {
            throw new RuntimeException('jwt_secret / api_url belum diatur');
        }

        $token = $this->signJwt($payload, $secret);
        $url = $apiUrl . '?token=' . rawurlencode($token);
        return $this->httpGet($url);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->b64url(json_encode($header, JSON_UNESCAPED_UNICODE)),
            $this->b64url(json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ];
        $input = implode('.', $segments);
        $segments[] = $this->b64url(hash_hmac('sha256', $input, $secret, true));
        return implode('.', $segments);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** @return mixed */
    private function httpGet(string $url)
    {
        $timeout = (int) ($this->config['timeout'] ?? 60);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            throw new RuntimeException('cURL: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('HTTP ' . $status . ': ' . (string) $body);
        }
        $decoded = json_decode((string) $body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}
