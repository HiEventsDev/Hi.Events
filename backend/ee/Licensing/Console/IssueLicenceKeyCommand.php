<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\Console;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\LicenceKeyCodec;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class IssueLicenceKeyCommand extends Command
{
    protected $signature = 'ee:licence:issue
        {--private-key= : File containing the base64 private key}
        {--kid= : Key id of that private key}
        {--customer= : Customer name shown in the admin licence page}
        {--expires= : Expiry date (YYYY-MM-DD, UTC)}
        {--plan=enterprise : Plan whose features the key unlocks (cloud is only for Hi.Events Cloud: it rolls features out by feature flag)}
        {--add=* : Add-on features on top of the plan, e.g. white_label}
        {--lid= : Licence id (generated when omitted)}';

    protected $description = 'Issue a signed Hi.Events licence key';

    public function handle(LicenceKeyCodec $codec): int
    {
        $privateKeyFile = (string) $this->option('private-key');
        $kid = (string) $this->option('kid');
        $customer = trim((string) $this->option('customer'));
        $expires = (string) $this->option('expires');

        if ($privateKeyFile === '' || $kid === '' || $customer === '' || $expires === '') {
            $this->error('--private-key, --kid, --customer and --expires are required.');

            return self::FAILURE;
        }

        if (! is_readable($privateKeyFile)) {
            $this->error("Cannot read $privateKeyFile.");

            return self::FAILURE;
        }

        $plan = Plan::tryFrom((string) $this->option('plan'));
        if ($plan === null) {
            $this->error('Unknown plan. Valid plans: '.implode(', ', array_column(Plan::cases(), 'value')));

            return self::FAILURE;
        }

        $addOns = (array) $this->option('add');
        $unknown = array_diff($addOns, array_column(LicensedFeature::cases(), 'value'));
        if ($unknown !== []) {
            $this->error('Unknown features: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        try {
            $expiresAt = CarbonImmutable::createFromFormat('!Y-m-d', $expires, 'UTC');
        } catch (Throwable) {
            $expiresAt = false;
        }
        if (! $expiresAt) {
            $this->error('--expires must be a date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $key = $codec->encode([
            'kid' => $kid,
            'lid' => (string) ($this->option('lid') ?: 'lic_'.Str::lower(Str::random(12))),
            'customer' => $customer,
            'issued_at' => CarbonImmutable::now('UTC')->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
            'plan' => $plan->value,
            'add' => array_values($addOns),
        ], trim((string) file_get_contents($privateKeyFile)));

        $this->line($key);

        return self::SUCCESS;
    }
}
