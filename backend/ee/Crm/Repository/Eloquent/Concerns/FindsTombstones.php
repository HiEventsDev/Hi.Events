<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns;

use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;

trait FindsTombstones
{
    /**
     * @param  string[]  $emails
     * @return array<string, string|null>
     */
    private function findTombstonesByEmail(int $organizerId, array $emails, string $salt): array
    {
        $tombstones = [];

        foreach (array_chunk($emails, 5000) as $chunk) {
            $emailsByHash = [];
            foreach ($chunk as $email) {
                $emailsByHash[DeletedContactHasher::hashWithSalt($salt, $email)] = $email;
            }

            $suppressions = $this->db->table('crm_deleted_contacts')
                ->where('organizer_id', $organizerId)
                ->whereIn('email_hash', array_keys($emailsByHash))
                ->pluck('suppression', 'email_hash')
                ->all();

            foreach ($suppressions as $hash => $suppression) {
                $tombstones[$emailsByHash[$hash]] = $suppression;
            }
        }

        return $tombstones;
    }
}
