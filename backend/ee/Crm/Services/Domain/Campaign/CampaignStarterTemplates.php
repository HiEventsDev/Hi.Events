<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignStarterDTO;

class CampaignStarterTemplates
{
    public const KEYS = ['event_announcement', 'last_chance', 'thank_you', 'newsletter'];

    /**
     * @return CampaignStarterDTO[]
     */
    public function all(): array
    {
        return array_map(fn (string $key) => $this->find($key), self::KEYS);
    }

    public function find(string $key): ?CampaignStarterDTO
    {
        return match ($key) {
            'event_announcement' => new CampaignStarterDTO(
                key: $key,
                name: __('Event announcement'),
                description: __('Tell people about a new event. Link the event to show it as a card with a ticket button.'),
                subject: __('Something new from :organizer', ['organizer' => '{{ organizer_name }}']),
                preheader: __('Tickets are on sale now.'),
                body: $this->paragraphs([
                    $this->greeting(),
                    __('We\'ve just announced a new event. Here are the details, and tickets are on sale now.'),
                    __('We\'d love to see you there.'),
                ]),
            ),
            'last_chance' => new CampaignStarterDTO(
                key: $key,
                name: __('Last chance'),
                description: __('A short reminder before tickets run out or sales close.'),
                subject: __('Last chance to get your tickets'),
                preheader: __('Sales close soon. Don\'t miss out.'),
                body: $this->paragraphs([
                    $this->greeting(),
                    __('Tickets are almost gone, and sales close soon. If you\'ve been meaning to come, now\'s the time.'),
                ]),
            ),
            'thank_you' => new CampaignStarterDTO(
                key: $key,
                name: __('Thank you for coming'),
                description: __('Thank people after an event and invite them back.'),
                subject: __('Thanks for coming'),
                preheader: __('It was great to see you.'),
                body: $this->paragraphs([
                    $this->greeting(),
                    __('Thank you for coming to :event. We hope you had a great time.', ['event' => $this->tokenWithFallback('last_event_title', __('our event'))]),
                    __('We\'re already planning what\'s next, and we\'ll let you know as soon as there\'s news.'),
                ]),
            ),
            'newsletter' => new CampaignStarterDTO(
                key: $key,
                name: __('Newsletter'),
                description: __('A regular update with news, highlights and what\'s coming up.'),
                subject: __('News from :organizer', ['organizer' => '{{ organizer_name }}']),
                preheader: __('Here\'s what\'s been happening, and what\'s next.'),
                body: '<h2>'.e(__('What\'s new')).'</h2>'.$this->paragraphs([
                    $this->greeting(),
                    __('Here\'s a quick update on what we\'ve been up to and what\'s coming up next.'),
                ]).'<hr><h2>'.e(__('Coming up')).'</h2>'.$this->paragraphs([
                    __('Share your upcoming events here.'),
                ]),
            ),
            default => null,
        };
    }

    private function greeting(): string
    {
        return __('Hi :name,', ['name' => $this->tokenWithFallback('first_name', __('there'))]);
    }

    private function tokenWithFallback(string $token, string $fallback): string
    {
        return '{{ '.$token.' | default: "'.str_replace(['"', '{', '}'], '', $fallback).'" }}';
    }

    /**
     * @param  string[]  $lines
     */
    private function paragraphs(array $lines): string
    {
        return implode('', array_map(static fn (string $line) => '<p>'.e($line).'</p>', $lines));
    }
}
