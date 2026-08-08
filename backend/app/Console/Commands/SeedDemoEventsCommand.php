<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use HiEvents\Console\Commands\Demo\ConferenceDemoEvent;
use HiEvents\Console\Commands\Demo\DemoOwner;
use HiEvents\Console\Commands\Demo\DemoSeedContext;
use HiEvents\Console\Commands\Demo\FestivalDemoEvent;
use HiEvents\Console\Commands\Demo\NightclubDemoEvent;
use HiEvents\Console\Commands\Demo\SeededDemoEvent;
use HiEvents\Console\Commands\Demo\YogaDemoEvent;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Organizer\CreateOrganizerHandler;
use HiEvents\Services\Application\Handlers\Organizer\DTO\CreateOrganizerDTO;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Throwable;

class SeedDemoEventsCommand extends Command
{
    protected $signature = 'demo:seed
        {--confirm : Required. Confirms you want to write demo data to this database}
        {--organizer-id= : Attach the events to an existing organizer instead of creating a demo account}
        {--email= : Email for the created demo account (defaults to demo+<timestamp>@example.com)}
        {--password=DemoPass123! : Password for the created demo account}
        {--only=* : Seed only these events: nightclub, conference, yoga, festival}
        {--timezone=Europe/Dublin : Timezone for the seeded events}
        {--currency=EUR : Currency code for the seeded events}';

    protected $description = 'Seed three fully-built demo events — an underground club night, a two-day tech conference and a recurring yoga studio schedule — with products, add-ons, checkout questions, promo codes, themes and cover images.';

    public function handle(
        DemoSeedContext $context,
        CreateAccountHandler $createAccountHandler,
        CreateOrganizerHandler $createOrganizerHandler,
        DatabaseManager $db,
    ): int {
        if (! $this->option('confirm')) {
            $this->error('demo:seed writes demo events, products and orders-facing data to '.$db->connection()->getDatabaseName().'.');
            $this->line('Re-run with --confirm once you are sure this is the right database.');

            return self::FAILURE;
        }

        $selected = $this->selectedEvents();

        if ($selected === null) {
            return self::FAILURE;
        }

        $timezone = (string) $this->option('timezone');
        $currency = strtoupper((string) $this->option('currency'));

        try {
            $owner = $this->resolveOwner($createAccountHandler, $createOrganizerHandler, $db, $timezone, $currency);
        } catch (Throwable $e) {
            $this->error('Could not resolve an account to seed into: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->warnIfAccountUnverified($db, $owner);

        $builders = [
            NightclubDemoEvent::KEY => fn () => (new NightclubDemoEvent($context, $timezone, $currency))->seed($owner),
            ConferenceDemoEvent::KEY => fn () => (new ConferenceDemoEvent($context, $timezone, $currency))->seed($owner),
            YogaDemoEvent::KEY => fn () => (new YogaDemoEvent($context, $timezone, $currency))->seed($owner),
            FestivalDemoEvent::KEY => fn () => (new FestivalDemoEvent($context, $timezone, $currency))->seed($owner),
        ];

        $seeded = [];

        foreach ($selected as $key) {
            $this->line('Seeding '.$key.' …');

            try {
                $seeded[] = $db->transaction($builders[$key]);
            } catch (Throwable $e) {
                $this->error('Failed while seeding '.$key.': '.$e->getMessage());
                $this->line($e->getFile().':'.$e->getLine());

                return self::FAILURE;
            }
        }

        $this->report($owner, $seeded);

        return self::SUCCESS;
    }

    private function selectedEvents(): ?array
    {
        $available = [NightclubDemoEvent::KEY, ConferenceDemoEvent::KEY, YogaDemoEvent::KEY, FestivalDemoEvent::KEY];
        $requested = (array) $this->option('only');

        if ($requested === []) {
            return $available;
        }

        $unknown = array_diff($requested, $available);

        if ($unknown !== []) {
            $this->error('Unknown --only value(s): '.implode(', ', $unknown));
            $this->line('Available: '.implode(', ', $available));

            return null;
        }

        return array_values(array_intersect($available, $requested));
    }

    private function resolveOwner(
        CreateAccountHandler $createAccountHandler,
        CreateOrganizerHandler $createOrganizerHandler,
        DatabaseManager $db,
        string $timezone,
        string $currency,
    ): DemoOwner {
        $organizerId = $this->option('organizer-id');

        if ($organizerId !== null) {
            $organizer = $db->table('organizers')->where('id', (int) $organizerId)->first();

            if ($organizer === null) {
                throw new RuntimeException('Organizer '.$organizerId.' does not exist.');
            }

            $userId = $db->table('account_users')
                ->where('account_id', $organizer->account_id)
                ->orderBy('id')
                ->value('user_id');

            if ($userId === null) {
                throw new RuntimeException('Account '.$organizer->account_id.' has no users.');
            }

            $this->authenticate((int) $userId);

            return new DemoOwner(
                account_id: (int) $organizer->account_id,
                organizer_id: (int) $organizer->id,
                user_id: (int) $userId,
            );
        }

        $email = $this->option('email') ?: 'demo+'.now()->format('YmdHis').'@example.com';
        $password = (string) $this->option('password');

        $account = $createAccountHandler->handle(new CreateAccountDTO(
            email: $email,
            password: $password,
            first_name: 'Demo',
            locale: 'en',
            last_name: 'Organiser',
            timezone: $timezone,
            currency_code: $currency,
        ));

        $user = User::where('email', $email)->firstOrFail();

        $db->table('accounts')
            ->where('id', $account->getId())
            ->whereNull('account_verified_at')
            ->update(['account_verified_at' => now()]);

        $this->authenticate($user->id);

        $organizer = $createOrganizerHandler->handle(new CreateOrganizerDTO(
            name: 'Stillroom, Subterra & Runtime',
            email: $email,
            account_id: $account->getId(),
            timezone: $timezone,
            currency: $currency,
        ));

        $this->newLine();
        $this->info('Created a demo account:');
        $this->line('  email    '.$email);
        $this->line('  password '.$password);
        $this->newLine();

        return new DemoOwner(
            account_id: $account->getId(),
            organizer_id: $organizer->getId(),
            user_id: $user->id,
        );
    }

    private function authenticate(int $userId): void
    {
        $user = User::find($userId);

        if ($user !== null) {
            auth()->login($user);
        }
    }

    private function warnIfAccountUnverified(DatabaseManager $db, DemoOwner $owner): void
    {
        $verifiedAt = $db->table('accounts')->where('id', $owner->account_id)->value('account_verified_at');

        if ($verifiedAt === null) {
            $this->warn('Account '.$owner->account_id.' has no account_verified_at — the seeded events will not be publicly visible until it is verified.');
        }
    }

    /**
     * @param  SeededDemoEvent[]  $seeded
     */
    private function report(DemoOwner $owner, array $seeded): void
    {
        $baseUrl = rtrim((string) config('app.frontend_url'), '/');

        $this->newLine();
        $this->info('Seeded '.count($seeded).' demo event(s) for organizer '.$owner->organizer_id.':');
        $this->newLine();

        $this->table(
            ['Event', 'ID', 'Occurrences', 'Promo codes'],
            array_map(static fn (SeededDemoEvent $event) => [
                mb_strimwidth($event->title, 0, 52, '…'),
                $event->event_id,
                $event->occurrence_count,
                implode(', ', $event->promo_codes),
            ], $seeded),
        );

        foreach ($seeded as $event) {
            $this->line($baseUrl.$event->publicPath());
        }
    }
}
