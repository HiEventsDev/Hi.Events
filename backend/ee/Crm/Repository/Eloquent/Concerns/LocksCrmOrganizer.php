<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent\Concerns;

trait LocksCrmOrganizer
{
    public function lockOrganizer(int $organizerId): void
    {
        $this->db->select("SELECT pg_advisory_xact_lock(hashtext('crm_projection'), ?)", [$organizerId]);
    }

    public function tryLockOrganizer(int $organizerId): bool
    {
        return (bool) $this->db->selectOne("SELECT pg_try_advisory_xact_lock(hashtext('crm_projection'), ?) AS locked", [$organizerId])->locked;
    }
}
