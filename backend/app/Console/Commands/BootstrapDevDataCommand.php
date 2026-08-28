<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Affiliate\CreateAffiliateHandler;
use HiEvents\Services\Application\Handlers\Affiliate\DTO\UpsertAffiliateDTO;
use HiEvents\Services\Application\Handlers\Event\CreateEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\GenerateOccurrencesFromRuleHandler;
use HiEvents\Services\Application\Handlers\Organizer\CreateOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use HiEvents\Services\Application\Handlers\Product\CreateProductHandler;
use HiEvents\Services\Application\Handlers\Product\DTO\UpsertProductDTO;
use HiEvents\Services\Application\Handlers\PromoCode\CreatePromoCodeHandler;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Domain\Auth\LoginService;
use HiEvents\Services\Domain\Product\DTO\ProductPriceDTO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BootstrapDevDataCommand extends Command
{
    protected $signature = 'dev:bootstrap
        {--email= : Account email (defaults to a unique agent+<timestamp>@dev.test)}
        {--password=BootstrapPass123! : Account password}
        {--force : Skip the non-production environment check}';

    protected $description = 'Create a verified superadmin account with organizer, single + recurring events, products, promo code and affiliate, then print the ids and a ready-to-use Bearer token for API testing.';

    public function handle(
        CreateAccountHandler $createAccountHandler,
        CreateOrganizerHandler $createOrganizerHandler,
        CreateEventHandler $createEventHandler,
        CreateProductHandler $createProductHandler,
        CreatePromoCodeHandler $createPromoCodeHandler,
        CreateAffiliateHandler $createAffiliateHandler,
        GenerateOccurrencesFromRuleHandler $generateOccurrencesHandler,
        LoginService $loginService,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production. Pass --force to override.');

            return self::FAILURE;
        }

        $this->seedCurrencyDefaultConfigurations();

        $email = $this->option('email') ?: 'agent+'.now()->format('YmdHis').'@dev.test';
        $password = $this->option('password');

        $account = $createAccountHandler->handle(new CreateAccountDTO(
            email: $email,
            password: $password,
            first_name: 'Agent',
            locale: 'en',
            last_name: 'Bootstrap',
            timezone: 'UTC',
            currency_code: 'USD',
        ));

        $user = User::where('email', $email)->firstOrFail();

        DB::table('accounts')->where('id', $account->getId())->update(['account_verified_at' => now()]);
        DB::table('account_users')->where('user_id', $user->id)->update(['role' => 'SUPERADMIN']);

        auth()->login($user);

        $organizer = $createOrganizerHandler->handle(new CreateOrganizerDTO(
            name: 'Bootstrap Organizer',
            email: $email,
            account_id: $account->getId(),
            timezone: 'UTC',
            currency: 'USD',
        ));

        $singleEvent = $createEventHandler->handle(new CreateEventDTO(
            title: 'Bootstrap Single Event',
            organizer_id: $organizer->getId(),
            account_id: $account->getId(),
            user_id: $user->id,
            start_date: now()->addDays(30)->setTime(19, 0)->toDateTimeString(),
            end_date: now()->addDays(30)->setTime(22, 0)->toDateTimeString(),
            timezone: 'UTC',
            currency: 'USD',
            category: EventCategory::MUSIC,
            status: EventStatus::LIVE->name,
        ));

        $recurringEvent = $createEventHandler->handle(new CreateEventDTO(
            title: 'Bootstrap Recurring Event',
            organizer_id: $organizer->getId(),
            account_id: $account->getId(),
            user_id: $user->id,
            timezone: 'UTC',
            currency: 'USD',
            category: EventCategory::MUSIC,
            status: EventStatus::LIVE->name,
            type: EventType::RECURRING,
        ));

        $generateOccurrencesHandler->handle(new GenerateOccurrencesDTO(
            event_id: $recurringEvent->getId(),
            recurrence_rule: [
                'range' => ['type' => 'count', 'count' => 4, 'start' => now()->addDays(7)->toDateString()],
                'interval' => 1,
                'frequency' => 'weekly',
                'days_of_week' => ['monday'],
                'times_of_day' => ['19:00'],
            ],
        ));

        $freeProduct = $this->createProduct($createProductHandler, $account->getId(), $singleEvent, 'Free Ticket', ProductPriceType::FREE, 0.0);
        $paidProduct = $this->createProduct($createProductHandler, $account->getId(), $singleEvent, 'Paid Ticket', ProductPriceType::PAID, 25.0, waitlistEnabled: true);
        $recurringProduct = $this->createProduct($createProductHandler, $account->getId(), $recurringEvent, 'Recurring Free Ticket', ProductPriceType::FREE, 0.0);

        $promoCode = $createPromoCodeHandler->handle($singleEvent->getId(), new UpsertPromoCodeDTO(
            code: 'bootstrap10',
            event_id: $singleEvent->getId(),
            applicable_product_ids: [],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 10.0,
            expiry_date: null,
            max_allowed_usages: null,
            discount_applies_to: PromoCodeDiscountAppliesToEnum::EACH_PRODUCT,
        ));

        $affiliate = $createAffiliateHandler->handle($singleEvent->getId(), $account->getId(), new UpsertAffiliateDTO(
            name: 'Bootstrap Affiliate',
            code: 'BOOTAFF',
            email: $email,
        ));

        $token = null;
        try {
            $token = $loginService->authenticate($email, $password, null)->token;
        } catch (Throwable $e) {
            $this->warn('Token mint failed ('.$e->getMessage().') — use the login curl below.');
        }

        $singleOccurrenceId = DB::table('event_occurrences')->where('event_id', $singleEvent->getId())->value('id');

        $this->table(['Key', 'Value'], [
            ['email', $email],
            ['password', $password],
            ['account_id', $account->getId()],
            ['user_id', $user->id],
            ['organizer_id', $organizer->getId()],
            ['single_event_id (LIVE)', $singleEvent->getId()],
            ['single_occurrence_id', $singleOccurrenceId],
            ['free_product_id / price_id', $freeProduct['product_id'].' / '.$freeProduct['price_id']],
            ['paid_product_id / price_id (waitlist on)', $paidProduct['product_id'].' / '.$paidProduct['price_id']],
            ['recurring_event_id (LIVE)', $recurringEvent->getId()],
            ['recurring_occurrence_ids', DB::table('event_occurrences')->where('event_id', $recurringEvent->getId())->pluck('id')->implode(', ')],
            ['recurring_product_id / price_id', $recurringProduct['product_id'].' / '.$recurringProduct['price_id']],
            ['promo_code', $promoCode->getCode()],
            ['affiliate_code', $affiliate->getCode()],
        ]);

        if ($token) {
            $this->info('Bearer token:');
            $this->line($token);
        }

        $this->newLine();
        $this->info('Examples:');
        $this->line('TOKEN=$(curl -sk -X POST https://localhost:8443/api/auth/login -H "Content-Type: application/json" -d \'{"email":"'.$email.'","password":"'.$password.'"}\' | python3 -c "import sys,json; print(json.load(sys.stdin)[\'token\'])")');
        $this->line('curl -sk https://localhost:8443/api/events/'.$singleEvent->getId().' -H "Authorization: Bearer $TOKEN"');
        $this->line('curl -sk https://localhost:8443/api/public/organizers/'.$organizer->getId().'/events?eventsStatus=upcoming');

        return self::SUCCESS;
    }

    private function createProduct(
        CreateProductHandler $handler,
        int $accountId,
        EventDomainObject $event,
        string $title,
        ProductPriceType $priceType,
        float $price,
        bool $waitlistEnabled = false,
    ): array {
        $categoryId = DB::table('product_categories')->where('event_id', $event->getId())->value('id');

        $product = $handler->handle(new UpsertProductDTO(
            account_id: $accountId,
            event_id: $event->getId(),
            product_category_id: $categoryId,
            title: $title,
            type: $priceType,
            product_type: ProductType::TICKET,
            prices: collect([new ProductPriceDTO(price: $price)]),
            waitlist_enabled: $waitlistEnabled,
        ));

        return [
            'product_id' => $product->getId(),
            'price_id' => DB::table('product_prices')->where('product_id', $product->getId())->value('id'),
        ];
    }

    private function seedCurrencyDefaultConfigurations(): void
    {
        $fees = [
            'USD' => 0.60,
            'EUR' => 0.50,
            'GBP' => 0.45,
            'AUD' => 0.85,
        ];

        foreach ($fees as $currency => $fixedFee) {
            $exists = DB::table('organizer_configurations')
                ->where('default_for_currency', $currency)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('organizer_configurations')->insert([
                'name' => "Standard ($currency)",
                'is_system_default' => false,
                'application_fees' => json_encode([
                    'percentage' => 1.25,
                    'fixed' => $fixedFee,
                    'currency' => $currency,
                ], JSON_THROW_ON_ERROR),
                'default_for_currency' => $currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
