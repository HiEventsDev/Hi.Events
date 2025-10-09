<?php

declare(strict_types=1);

namespace HiEvents\Providers;

use HiEvents\Repository\Eloquent\AccountConfigurationRepository;
use HiEvents\Repository\Eloquent\AccountRepository;
use HiEvents\Repository\Eloquent\AccountStripePlatformRepository;
use HiEvents\Repository\Eloquent\AccountUserRepository;
use HiEvents\Repository\Eloquent\AffiliateRepository;
use HiEvents\Repository\Eloquent\AttendeeCheckInRepository;
use HiEvents\Repository\Eloquent\AttendeeRepository;
use HiEvents\Repository\Eloquent\CapacityAssignmentRepository;
use HiEvents\Repository\Eloquent\CheckInListRepository;
use HiEvents\Repository\Eloquent\EmailTemplateRepository;
use HiEvents\Repository\Eloquent\EventDailyStatisticRepository;
use HiEvents\Repository\Eloquent\EventRepository;
use HiEvents\Repository\Eloquent\EventSettingsRepository;
use HiEvents\Repository\Eloquent\EventStatisticRepository;
use HiEvents\Repository\Eloquent\ImageRepository;
use HiEvents\Repository\Eloquent\InvoiceRepository;
use HiEvents\Repository\Eloquent\MessageRepository;
use HiEvents\Repository\Eloquent\OrderApplicationFeeRepository;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use HiEvents\Repository\Eloquent\OrderRefundRepository;
use HiEvents\Repository\Eloquent\OrderRepository;
use HiEvents\Repository\Eloquent\OrganizerRepository;
use HiEvents\Repository\Eloquent\OrganizerSettingsRepository;
use HiEvents\Repository\Eloquent\OutgoingMessageRepository;
use HiEvents\Repository\Eloquent\PasswordResetRepository;
use HiEvents\Repository\Eloquent\PasswordResetTokenRepository;
use HiEvents\Repository\Eloquent\ProductCategoryRepository;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use HiEvents\Repository\Eloquent\ProductRepository;
use HiEvents\Repository\Eloquent\PromoCodeRepository;
use HiEvents\Repository\Eloquent\QuestionAndAnswerViewRepository;
use HiEvents\Repository\Eloquent\QuestionAnswerRepository;
use HiEvents\Repository\Eloquent\QuestionRepository;
use HiEvents\Repository\Eloquent\StripeCustomerRepository;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\TaxAndFeeRepository;
use HiEvents\Repository\Eloquent\UserRepository;
use HiEvents\Repository\Eloquent\WebhookLogRepository;
use HiEvents\Repository\Eloquent\WebhookRepository;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeCheckInRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CapacityAssignmentRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderApplicationFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetRepositoryInterface;
use HiEvents\Repository\Interfaces\PasswordResetTokenRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAndAnswerViewRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Repository\Interfaces\StripeCustomerRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Repository\Interfaces\TaxAndFeeRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookLogRepositoryInterface;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
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
        ProductRepositoryInterface::class => ProductRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        AttendeeRepositoryInterface::class => AttendeeRepository::class,
        AffiliateRepositoryInterface::class => AffiliateRepository::class,
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
        ProductPriceRepositoryInterface::class => ProductPriceRepository::class,
        EventStatisticRepositoryInterface::class => EventStatisticRepository::class,
        EventDailyStatisticRepositoryInterface::class => EventDailyStatisticRepository::class,
        EventSettingsRepositoryInterface::class => EventSettingsRepository::class,
        OrganizerRepositoryInterface::class => OrganizerRepository::class,
        AccountUserRepositoryInterface::class => AccountUserRepository::class,
        CapacityAssignmentRepositoryInterface::class => CapacityAssignmentRepository::class,
        StripeCustomerRepositoryInterface::class => StripeCustomerRepository::class,
        CheckInListRepositoryInterface::class => CheckInListRepository::class,
        AttendeeCheckInRepositoryInterface::class => AttendeeCheckInRepository::class,
        ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
        InvoiceRepositoryInterface::class => InvoiceRepository::class,
        OrderRefundRepositoryInterface::class => OrderRefundRepository::class,
        WebhookRepositoryInterface::class => WebhookRepository::class,
        WebhookLogRepositoryInterface::class => WebhookLogRepository::class,
        OrderApplicationFeeRepositoryInterface::class => OrderApplicationFeeRepository::class,
        AccountConfigurationRepositoryInterface::class => AccountConfigurationRepository::class,
        QuestionAndAnswerViewRepositoryInterface::class => QuestionAndAnswerViewRepository::class,
        OutgoingMessageRepositoryInterface::class => OutgoingMessageRepository::class,
        OrganizerSettingsRepositoryInterface::class => OrganizerSettingsRepository::class,
        EmailTemplateRepositoryInterface::class => EmailTemplateRepository::class,
        AccountStripePlatformRepositoryInterface::class => AccountStripePlatformRepository::class,
    ];

    public function register(): void
    {
        foreach (self::$interfaceToConcreteMap as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
