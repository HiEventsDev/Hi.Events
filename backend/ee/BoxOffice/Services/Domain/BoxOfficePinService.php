<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;

class BoxOfficePinService
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly Repository $config,
    ) {}

    public function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function hash(string $pin): string
    {
        return $this->hasher->make($this->keyedPin($pin));
    }

    public function verify(string $pin, string $hash): bool
    {
        return $this->hasher->check($this->keyedPin($pin), $hash);
    }

    private function keyedPin(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) $this->config->get('app.key'));
    }
}
