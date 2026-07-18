<?php

namespace App\Support;

final class AuditFingerprint
{
    /**
     * @return array{version: string, identifier: string, network: string}|null
     */
    public function loginFailure(string $identifier, ?string $networkAddress): ?array
    {
        $version = (string) config('audit.login_failure_fingerprint.active_version', 'v1');
        $key = (string) config("audit.login_failure_fingerprint.keys.{$version}", '');

        if ($key === '') {
            return null;
        }

        return [
            'version' => $version,
            'identifier' => $this->hmac($key, 'identifier', mb_strtolower(trim($identifier))),
            'network' => $this->hmac($key, 'network', trim((string) $networkAddress)),
        ];
    }

    private function hmac(string $key, string $purpose, string $value): string
    {
        $bounded = mb_substr($value, 0, 512);

        return hash_hmac('sha256', $purpose."\0".$bounded, $key);
    }
}
