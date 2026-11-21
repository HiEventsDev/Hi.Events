<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\ShareCaptionTone;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ShareSuggestionGenerationFailedException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\ShareCaptionDTO;
use HiEvents\Services\Infrastructure\Ai\Agents\EventShareCaptionAgent;
use Throwable;

class EventShareCaptionService
{
    private const int TITLE_MAX_LENGTH = 300;

    private const int DESCRIPTION_MAX_LENGTH = 2000;

    private const int NAME_MAX_LENGTH = 200;

    private const int CAPTION_MAX_LENGTH = 280;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    /**
     * @return ShareCaptionDTO[]
     *
     * @throws ShareSuggestionGenerationFailedException
     */
    public function generate(int $eventId): array
    {
        $prompt = $this->buildPrompt($eventId);

        try {
            $response = (new EventShareCaptionAgent)->prompt($prompt);
        } catch (Throwable $exception) {
            throw new ShareSuggestionGenerationFailedException($exception->getMessage(), previous: $exception);
        }

        $captions = $this->toCaptions((array) ($response['captions'] ?? []));

        if ($captions === []) {
            throw new ShareSuggestionGenerationFailedException('The model returned no usable captions');
        }

        return $captions;
    }

    private function buildPrompt(int $eventId): string
    {
        /** @var EventDomainObject $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, nested: [
                new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->findById($eventId);

        $facts = implode("\n", array_filter([
            '<title>'.$this->toClippedText($event->getTitle(), self::TITLE_MAX_LENGTH).'</title>',
            '<description>'.$this->toClippedText($event->getDescription(), self::DESCRIPTION_MAX_LENGTH).'</description>',
            '<organizer_name>'.$this->toClippedText($event->getOrganizer()?->getName(), self::NAME_MAX_LENGTH).'</organizer_name>',
            $this->nextDateFact($event),
            $this->venueFact($event),
        ]));

        return <<<PROMPT
        <event>
        {$facts}
        </event>
        PROMPT;
    }

    private function nextDateFact(EventDomainObject $event): ?string
    {
        $nextOccurrence = $this->occurrenceRepository->findWhere(
            where: [
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $event->getId(),
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
                [EventOccurrenceDomainObjectAbstract::START_DATE, '>=', Carbon::now()->toDateTimeString()],
            ],
            orderAndDirections: [new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE)],
            limit: 1,
        )->first();

        if ($nextOccurrence === null) {
            return null;
        }

        $startsAt = Carbon::parse($nextOccurrence->getStartDate(), 'UTC')->setTimezone($event->getTimezone());

        return '<next_date>'.$startsAt->format('l j F Y, H:i').' ('.$event->getTimezone().')</next_date>';
    }

    private function venueFact(EventDomainObject $event): ?string
    {
        $venueName = $this->toClippedText($event->getEventLocation()?->getLocation()?->getName(), self::NAME_MAX_LENGTH);

        return $venueName === '' ? null : '<venue>'.$venueName.'</venue>';
    }

    /**
     * @return ShareCaptionDTO[]
     */
    private function toCaptions(array $rawCaptions): array
    {
        $captions = [];

        foreach ($rawCaptions as $rawCaption) {
            $tone = ShareCaptionTone::tryFrom((string) ($rawCaption['tone'] ?? ''));
            $text = $this->toCaptionText((string) ($rawCaption['text'] ?? ''));

            if ($tone === null || $text === '' || isset($captions[$tone->value])) {
                continue;
            }

            $captions[$tone->value] = new ShareCaptionDTO(tone: $tone, text: $text);
        }

        return array_values($captions);
    }

    private function toCaptionText(string $rawText): string
    {
        $text = $this->toPlainText((string) preg_replace('/\s*—\s*/u', ', ', $rawText));

        if (mb_strlen($text) <= self::CAPTION_MAX_LENGTH) {
            return $text;
        }

        $clipped = mb_substr($text, 0, self::CAPTION_MAX_LENGTH);

        if (preg_match('/^.+[.!?。！？](?=\s|$)/us', $clipped, $wholeSentences) === 1) {
            return $wholeSentences[0];
        }

        return rtrim((string) preg_replace('/\s+\S*$/u', '', $clipped));
    }

    private function toClippedText(?string $value, int $maxLength): string
    {
        return mb_substr($this->toPlainText($value), 0, $maxLength);
    }

    private function toPlainText(?string $value): string
    {
        $decoded = html_entity_decode(strip_tags($value ?? ''), ENT_QUOTES | ENT_HTML5);
        $text = str_replace(['<', '>'], ['‹', '›'], $decoded);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
