<?php

namespace HiEvents\Services\Infrastructure\TokenGenerator;

use InvalidArgumentException;
use Random\Randomizer;

class TokenGeneratorService
{
    public function __construct(private readonly Randomizer $randomizer)
    {
    }

    /**
     * Generates a random token string.
     *
     * @param int    $length Desired length of the random part of the token.
     * @param string $prefix A prefix to be added to the token.
     *
     * @throws InvalidArgumentException if the length is not positive.
     *
     * @return string Generated token string with prefix.
     */
    public function generateToken(int $length = 32, string $prefix = ''): string
    {
        if ($length <= 0) {
            throw new InvalidArgumentException(__('Length must be a positive integer.'));
        }

        // Adjust length to account for prefix
        $adjustedLength = ($length - strlen($prefix)) / 2; // Because bin2hex doubles the length
        if ($adjustedLength <= 0) {
            throw new InvalidArgumentException(__('Prefix length exceeds the total desired token length.'));
        }

        $randomBytes = $this->randomizer->getBytes($adjustedLength);
        $token = bin2hex($randomBytes);

        return $prefix . $token;
    }
}
