<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use Mockery;
use Tests\TestCase;

class DeletedContactHasherTest extends TestCase
{
    private const EMAIL = 'ada@example.com';

    public function test_the_hash_is_stable_for_the_same_organizer_and_email(): void
    {
        $hasher = $this->hasher([7 => 'salt-seven']);

        $this->assertSame($hasher->hash(7, self::EMAIL), $hasher->hash(7, self::EMAIL));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hasher->hash(7, self::EMAIL));
    }

    public function test_the_hash_differs_per_organizer_salt_and_email(): void
    {
        $hasher = $this->hasher([7 => 'salt-seven', 8 => 'salt-eight']);
        $hash = $hasher->hash(7, self::EMAIL);

        $this->assertNotSame($hash, $hasher->hash(8, self::EMAIL));
        $this->assertNotSame($hash, $hasher->hash(7, 'grace@example.com'));
    }

    public function test_the_instance_and_static_hashes_agree(): void
    {
        $hasher = $this->hasher([7 => 'salt-seven']);

        $this->assertSame(DeletedContactHasher::hashWithSalt('salt-seven', self::EMAIL), $hasher->hash(7, self::EMAIL));
        $this->assertStringNotContainsString('ada', $hasher->hash(7, self::EMAIL));
    }

    /**
     * @param  array<int, string>  $salts
     */
    private function hasher(array $salts): DeletedContactHasher
    {
        $repository = Mockery::mock(CrmContactRepositoryInterface::class);
        foreach ($salts as $organizerId => $salt) {
            $repository->shouldReceive('findOrCreateDeletedContactSalt')->with($organizerId)->andReturn($salt);
        }

        return new DeletedContactHasher($repository);
    }
}
