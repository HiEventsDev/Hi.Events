<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Message;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\AccountMessagingTierDomainObject;
use HiEvents\DomainObjects\Enums\MessagingTierViolationEnum;
use HiEvents\Repository\Interfaces\AccountMessagingTierRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Message\MessagingEligibilityService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessagingEligibilityLinkDetectionTest extends TestCase
{
    public static function contents(): array
    {
        return [
            'an image uploaded to our CDN' => ['<p>Hi</p><img src="https://cdn.example.com/photo.jpg" alt="Stage">', false],
            'an image hosted anywhere else' => ['<p>Hi</p><img src="https://tracker.example.net/pixel.gif" alt="">', true],
            'a link' => ['<p><a href="https://example.com">Tickets</a></p>', true],
            'a bare url in text' => ['<p>Visit https://example.com today</p>', true],
            'an image and a link' => ['<img src="https://cdn.example.com/a.jpg"><a href="https://example.com">x</a>', true],
        ];
    }

    #[DataProvider('contents')]
    public function test_links_bare_urls_and_images_not_on_our_cdn_count_as_links(string $content, bool $isViolation): void
    {
        config(['app.cdn_url' => 'https://cdn.example.com']);

        $accounts = Mockery::mock(AccountRepositoryInterface::class);
        $accounts->shouldReceive('findById')->andReturn((new AccountDomainObject)->setAccountMessagingTierId(1));
        $tiers = Mockery::mock(AccountMessagingTierRepositoryInterface::class);
        $tiers->shouldReceive('findFirstWhere')->andReturn(
            (new AccountMessagingTierDomainObject)->setName('Untrusted')->setLinksAllowed(false)->setMaxMessagesPer24h(100)->setMaxRecipientsPerMessage(100),
        );
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('countMessagesInLast24Hours')->andReturn(0);

        $service = new MessagingEligibilityService(
            $accounts,
            Mockery::mock(EventRepositoryInterface::class),
            $messages,
            $tiers,
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(OrganizerRepositoryInterface::class),
        );

        $violation = $service->checkTierLimits(1, 1, $content);

        $this->assertSame($isViolation, $violation !== null && in_array(MessagingTierViolationEnum::LINKS_NOT_ALLOWED, $violation->violations, true));
    }
}
