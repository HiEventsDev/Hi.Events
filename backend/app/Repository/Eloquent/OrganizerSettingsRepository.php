<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Models\OrganizerSetting;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;

/**
 * @extends BaseRepository<OrganizerSettingDomainObject>
 */
class OrganizerSettingsRepository extends BaseRepository implements OrganizerSettingsRepositoryInterface
{
    public function setNewEventNotificationsEnabled(int $organizerId, bool $enabled): void
    {
        $this->runQuery(fn () => $this->db->transaction(function () use ($organizerId, $enabled) {
            $settings = $this->db->table('organizer_settings')->where('organizer_id', $organizerId)->lockForUpdate()->first();
            if ($settings !== null && (bool) $settings->new_event_notifications_enabled !== $enabled) {
                $this->db->table('organizer_settings')->where('organizer_id', $organizerId)->update([
                    'new_event_notifications_enabled' => $enabled,
                    'new_event_notifications_generation' => $settings->new_event_notifications_generation + 1,
                    'updated_at' => now(),
                ]);
            }
        }));
    }

    protected function getModel(): string
    {
        return OrganizerSetting::class;
    }

    public function getDomainObject(): string
    {
        return OrganizerSettingDomainObject::class;
    }
}
