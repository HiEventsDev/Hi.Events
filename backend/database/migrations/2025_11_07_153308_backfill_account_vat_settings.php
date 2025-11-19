<?php

use HiEvents\DomainObjects\Enums\CountryCode;
use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Models\AccountStripePlatform;
use HiEvents\Models\AccountVatSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!config('app.is_hi_events')) {
            return;
        }

        /** @var Collection<AccountStripePlatform> $stripeAccounts */
        $stripeAccounts = AccountStripePlatform::query()
            ->whereNotNull('stripe_setup_completed_at')
            ->get();

        foreach ($stripeAccounts as $accountStripePlatform) {
            $stripeCountry = $accountStripePlatform->stripe_account_details['country'] ?? null;

            if ($stripeCountry === null) {
                continue;
            }

            if (CountryCode::isEuCountry(CountryCode::from(strtoupper($stripeCountry)))) {
                $vatSettings = new AccountVatSetting();
                $vatSettings->account()->associate($accountStripePlatform->account);
                $vatSettings->vat_country_code = strtoupper($stripeCountry);
                $vatSettings->vat_validated = false;
                $vatSettings->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //no-op
    }
};
