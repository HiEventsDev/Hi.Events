<?php

declare(strict_types=1);

namespace HiEvents\Providers;

use HiEvents\Repository\Eloquent\AccountRepository;
use HiEvents\Repository\Eloquent\AccountUserRepository;
use HiEvents\Repository\Eloquent\AttendeeCheckInRepository;
use HiEvents\Repository\Eloquent\AttendeeRepository;
use HiEvents\Repository\Eloquent\CapacityAssignmentRepository;
use HiEvents\Repository\Eloquent\CheckInListRepository;
use HiEvents\Repository\Eloquent\EventDailyStatisticRepository;
use HiEvents\Repository\Eloquent\EventRepository;
use HiEvents\Repository\Eloquent\EventSettingsRepository;
use HiEvents\Repository\Eloquent\EventStatisticRepository;
use HiEvents\Repository\Eloquent\ImageRepository;
use HiEvents\Repository\Eloquent\MessageRepository;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use HiEvents\Repository\Eloquent\OrderRepository;
use HiEvents\Repository\Eloquent\OrganizerRepository;
use HiEvents\Repository\Eloquent\PasswordResetRepository;
use HiEvents\Repository\Eloquent\PasswordResetTokenRepository;
use HiEvents\Repository\Eloquent\PromoCodeRepository;
use HiEvents\Repository\Eloquent\QuestionAnswerRepository;
use HiEvents\Repository\Eloquent\QuestionRepository;
use HiEvents\Repository\Eloquent\StripeCustomerRepository;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\TaxAndFeeRepository;
use HiEvents\Repository\Eloquent\TicketPriceRepository;
use HiEvents\Repository\Eloquent\TicketRepository;
use HiEvents\Repository\Eloquent\UserRepository;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

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
        AccountUserRepositoryInterface::class => AccountUserRepository::class,
        CapacityAssignmentRepositoryInterface::class => CapacityAssignmentRepository::class,
        StripeCustomerRepositoryInterface::class => StripeCustomerRepository::class,
        CheckInListRepositoryInterface::class => CheckInListRepository::class,
        AttendeeCheckInRepositoryInterface::class => AttendeeCheckInRepository::class,
    ];

    public function register(): void
    {
        foreach (self::$interfaceToConcreteMap as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
