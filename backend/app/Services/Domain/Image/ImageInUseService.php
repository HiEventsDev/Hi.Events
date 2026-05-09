<?php

namespace HiEvents\Services\Domain\Image;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Domain\Image\DTO\ImageReferenceDTO;
use Illuminate\Support\Facades\DB;

class ImageInUseService
{
    private const ENTITY_EVENT = 'event';

    private const ENTITY_ORGANIZER = 'organizer';

    private const ENTITY_EVENT_EMAIL_TEMPLATE = 'event_email_template';

    private const ENTITY_ORGANIZER_EMAIL_TEMPLATE = 'organizer_email_template';

    private const ENTITY_ACCOUNT_EMAIL_TEMPLATE = 'account_email_template';

    /**
     * Scan rich-text columns for references to the image at $imagePath.
     *
     * @return ImageReferenceDTO[]
     */
    public function findReferences(string $imagePath, int $accountId): array
    {
        if ($imagePath === '') {
            return [];
        }

        $needle = '%'.$imagePath.'%';

        return [
            ...$this->scanEvents($accountId, $needle),
            ...$this->scanEventSettings($accountId, $needle),
            ...$this->scanEmailTemplates($accountId, $needle),
            ...$this->scanOrganizers($accountId, $needle),
        ];
    }

    /** @return ImageReferenceDTO[] */
    private function scanEvents(int $accountId, string $needle): array
    {
        $rows = DB::table('events')
            ->select(['id', 'title', 'status', 'start_date', 'end_date', 'timezone'])
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->where('description', 'like', $needle)
            ->get();

        $refs = [];
        foreach ($rows as $row) {
            $refs[] = new ImageReferenceDTO(
                entity_type: self::ENTITY_EVENT,
                entity_id: (int) $row->id,
                entity_name: (string) $row->title,
                field_label: __('Event description'),
                is_protected: $this->isEventProtected($row->status, $row->start_date, $row->end_date, $row->timezone),
                event_status: $row->status,
                event_lifecycle_status: $this->lifecycleStatus($row->start_date, $row->end_date, $row->timezone),
            );
        }

        return $refs;
    }

    /** @return ImageReferenceDTO[] */
    private function scanEventSettings(int $accountId, string $needle): array
    {
        $columns = [
            'email_footer_message' => __('Email footer'),
            'post_checkout_message' => __('Post-checkout message'),
            'offline_payment_instructions' => __('Offline payment instructions'),
        ];

        $refs = [];
        foreach ($columns as $column => $label) {
            $rows = DB::table('event_settings as es')
                ->join('events as e', 'e.id', '=', 'es.event_id')
                ->select(['e.id', 'e.title', 'e.status', 'e.start_date', 'e.end_date', 'e.timezone'])
                ->where('e.account_id', $accountId)
                ->whereNull('e.deleted_at')
                ->whereNull('es.deleted_at')
                ->where("es.$column", 'like', $needle)
                ->get();

            foreach ($rows as $row) {
                $refs[] = new ImageReferenceDTO(
                    entity_type: self::ENTITY_EVENT,
                    entity_id: (int) $row->id,
                    entity_name: (string) $row->title,
                    field_label: $label,
                    is_protected: $this->isEventProtected($row->status, $row->start_date, $row->end_date, $row->timezone),
                    event_status: $row->status,
                    event_lifecycle_status: $this->lifecycleStatus($row->start_date, $row->end_date, $row->timezone),
                );
            }
        }

        return $refs;
    }

    /** @return ImageReferenceDTO[] */
    private function scanEmailTemplates(int $accountId, string $needle): array
    {
        $rows = DB::table('email_templates as t')
            ->leftJoin('events as e', 'e.id', '=', 't.event_id')
            ->leftJoin('organizers as o', 'o.id', '=', 't.organizer_id')
            ->select([
                't.id', 't.template_type', 't.event_id', 't.organizer_id',
                'e.title as event_title', 'e.status as event_status',
                'e.start_date', 'e.end_date', 'e.timezone',
                'o.name as organizer_name',
            ])
            ->where('t.account_id', $accountId)
            ->whereNull('t.deleted_at')
            ->where('t.body', 'like', $needle)
            ->get();

        $refs = [];
        foreach ($rows as $row) {
            $typeLabel = $this->emailTemplateTypeLabel($row->template_type);

            if ($row->event_id !== null) {
                $refs[] = new ImageReferenceDTO(
                    entity_type: self::ENTITY_EVENT_EMAIL_TEMPLATE,
                    entity_id: (int) $row->id,
                    entity_name: (string) ($row->event_title ?? __('Event')),
                    field_label: __('Email template (:type)', ['type' => $typeLabel]),
                    is_protected: $this->isEventProtected($row->event_status, $row->start_date, $row->end_date, $row->timezone),
                    event_status: $row->event_status,
                    event_lifecycle_status: $this->lifecycleStatus($row->start_date, $row->end_date, $row->timezone),
                );
            } elseif ($row->organizer_id !== null) {
                $refs[] = new ImageReferenceDTO(
                    entity_type: self::ENTITY_ORGANIZER_EMAIL_TEMPLATE,
                    entity_id: (int) $row->id,
                    entity_name: (string) ($row->organizer_name ?? __('Organizer')),
                    field_label: __('Email template (:type)', ['type' => $typeLabel]),
                    is_protected: false,
                );
            } else {
                $refs[] = new ImageReferenceDTO(
                    entity_type: self::ENTITY_ACCOUNT_EMAIL_TEMPLATE,
                    entity_id: (int) $row->id,
                    entity_name: __('Account-level template'),
                    field_label: __('Email template (:type)', ['type' => $typeLabel]),
                    is_protected: false,
                );
            }
        }

        return $refs;
    }

    /** @return ImageReferenceDTO[] */
    private function scanOrganizers(int $accountId, string $needle): array
    {
        $rows = DB::table('organizers')
            ->select(['id', 'name'])
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->where('description', 'like', $needle)
            ->get();

        $refs = [];
        foreach ($rows as $row) {
            $refs[] = new ImageReferenceDTO(
                entity_type: self::ENTITY_ORGANIZER,
                entity_id: (int) $row->id,
                entity_name: (string) $row->name,
                field_label: __('Organizer description'),
                is_protected: false,
            );
        }

        return $refs;
    }

    /**
     * Event is protected from hard-deletion of referenced images when it is
     * neither archived nor in the past (i.e. DRAFT/LIVE and UPCOMING/ONGOING).
     */
    private function isEventProtected(?string $status, ?string $startDate, ?string $endDate, ?string $timezone): bool
    {
        if ($status === EventStatus::ARCHIVED->name) {
            return false;
        }

        return $this->lifecycleStatus($startDate, $endDate, $timezone) !== EventLifecycleStatus::ENDED->name;
    }

    private function lifecycleStatus(?string $startDate, ?string $endDate, ?string $timezone): string
    {
        if ($endDate === null && $startDate === null) {
            return EventLifecycleStatus::UPCOMING->name;
        }

        $tz = $timezone ?: 'UTC';
        $now = now($tz);

        if ($endDate !== null && now()->parse($endDate)->setTimezone($tz)->lessThan($now)) {
            return EventLifecycleStatus::ENDED->name;
        }

        if ($startDate !== null && now()->parse($startDate)->setTimezone($tz)->greaterThan($now)) {
            return EventLifecycleStatus::UPCOMING->name;
        }

        return EventLifecycleStatus::ONGOING->name;
    }

    private function emailTemplateTypeLabel(?string $type): string
    {
        if ($type === null) {
            return '';
        }
        $enum = EmailTemplateType::tryFrom($type);

        return $enum?->label() ?? $type;
    }
}
