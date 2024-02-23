<?php

declare(strict_types=1);

namespace TicketKitten\Providers;

use Illuminate\Support\ServiceProvider;
use TicketKitten\Repository\Eloquent\AccountRepository;
use TicketKitten\Repository\Eloquent\AttendeeRepository;
use TicketKitten\Repository\Eloquent\EventSettingsRepository;
use TicketKitten\Repository\Eloquent\EventDailyStatisticRepository;
use TicketKitten\Repository\Eloquent\EventRepository;
use TicketKitten\Repository\Eloquent\EventStatisticRepository;
use TicketKitten\Repository\Eloquent\ImageRepository;
use TicketKitten\Repository\Eloquent\MessageRepository;
use TicketKitten\Repository\Eloquent\OrderItemRepository;
use TicketKitten\Repository\Eloquent\OrderRepository;
use TicketKitten\Repository\Eloquent\OrganizerRepository;
use TicketKitten\Repository\Eloquent\PasswordResetRepository;
use TicketKitten\Repository\Eloquent\PasswordResetTokenRepository;
use TicketKitten\Repository\Eloquent\PromoCodeRepository;
use TicketKitten\Repository\Eloquent\QuestionAnswerRepository;
use TicketKitten\Repository\Eloquent\QuestionRepository;
use TicketKitten\Repository\Eloquent\StripePaymentsRepository;
use TicketKitten\Repository\Eloquent\TaxAndFeeRepository;
use TicketKitten\Repository\Eloquent\TicketPriceRepository;
use TicketKitten\Repository\Eloquent\TicketRepository;
use TicketKitten\Repository\Eloquent\UserRepository;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventSettingsRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventStatisticRepositoryInterface;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;
use TicketKitten\Repository\Interfaces\MessageRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderItemRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrganizerRepositoryInterface;
use TicketKitten\Repository\Interfaces\PasswordResetRepositoryInterface;
use TicketKitten\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use TicketKitten\Repository\Interfaces\QuestionRepositoryInterface;
use TicketKitten\Repository\Interfaces\StripePaymentsRepositoryInterface;
use TicketKitten\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketPriceRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @todo - find a way to auto-bind these
     */
    private static array $interfaceToConcreteMap = [
        UserRepositoryInterface::class => UserRepository::class,
        AccountRepositoryInterface::class => AccountRepository::class,
        EventRepositoryInterface::class => EventRepository::class,
        TicketRepositoryInterface::class => TicketRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        AttendeeRepositoryInterface::class => AttendeeRepository::class,
        OrderItemRepositoryInterface::class => OrderItemRepository::class,
        QuestionRepositoryInterface::class => QuestionRepository::class,
        QuestionAnswerRepositoryInterface::class => QuestionAnswerRepository::class,
        StripePaymentsRepositoryInterface::class => StripePaymentsRepository::class,
        PromoCodeRepositoryInterface::class => PromoCodeRepository::class,
        MessageRepositoryInterface::class => MessageRepository::class,
        PasswordResetTokenRepositoryInterface::class => PasswordResetTokenRepository::class,
        PasswordResetRepositoryInterface::class => PasswordResetRepository::class,
        TaxAndFeeRepositoryInterface::class => TaxAndFeeRepository::class,
        ImageRepositoryInterface::class => ImageRepository::class,
        TicketPriceRepositoryInterface::class => TicketPriceRepository::class,
        EventStatisticRepositoryInterface::class => EventStatisticRepository::class,
        EventDailyStatisticRepositoryInterface::class => EventDailyStatisticRepository::class,
        EventSettingsRepositoryInterface::class => EventSettingsRepository::class,
        OrganizerRepositoryInterface::class => OrganizerRepository::class,
    ];

    public function register(): void
    {
        foreach (self::$interfaceToConcreteMap as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
