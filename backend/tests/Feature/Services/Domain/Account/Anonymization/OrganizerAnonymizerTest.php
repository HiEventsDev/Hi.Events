<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Account\Anonymization;

use HiEvents\Models\Account;
use HiEvents\Models\AccountUser;
use HiEvents\Models\Organizer;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Account\AccountAnonymizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizerAnonymizerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_anonymization_scrubs_organizer_settings_free_text(): void
    {
        $account = $this->seedAccount('acc_orgset_doomed', 'doomed-owner@example.com');
        $organizer = $this->seedOrganizer($account->id, 'Doomed Organizer');
        $settingsId = $this->seedOrganizerSettings($organizer->id);

        $survivingAccount = $this->seedAccount('acc_orgset_survivor', 'surviving-owner@example.com');
        $survivingOrganizer = $this->seedOrganizer($survivingAccount->id, 'Surviving Organizer');
        $survivingSettingsId = $this->seedOrganizerSettings($survivingOrganizer->id);

        $manifest = $this->app->make(AccountAnonymizationService::class)
            ->anonymizeAccount($account->id);

        $settings = DB::table('organizer_settings')->where('id', $settingsId)->first();
        $this->assertNull($settings->homepage_sections);
        $this->assertNull($settings->website_url);
        $this->assertNull($settings->location_details);
        $this->assertNull($settings->social_media_handles);
        $this->assertNull($settings->seo_title);
        $this->assertNull($settings->seo_description);
        $this->assertNull($settings->seo_keywords);

        $survivingSettings = DB::table('organizer_settings')->where('id', $survivingSettingsId)->first();
        $this->assertStringContainsString('call Jane on 555-0100', $survivingSettings->homepage_sections);
        $this->assertSame('https://jane.example.com', $survivingSettings->website_url);
        $this->assertNotNull($survivingSettings->location_details);
        $this->assertNotNull($survivingSettings->social_media_handles);
        $this->assertSame('Jane Doe Events', $survivingSettings->seo_title);

        $this->assertContains('organizer_settings', array_column($manifest, 'entity'));
    }

    private function seedAccount(string $shortId, string $email): Account
    {
        $account = Account::forceCreate([
            'name' => 'Account '.$shortId,
            'email' => $email,
            'timezone' => 'UTC',
            'currency_code' => 'USD',
            'short_id' => $shortId,
        ]);

        $user = User::forceCreate([
            'email' => 'user-'.$email,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'password' => 'hash',
            'timezone' => 'UTC',
        ]);

        AccountUser::forceCreate([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'is_account_owner' => true,
        ]);

        return $account;
    }

    private function seedOrganizer(int $accountId, string $name): Organizer
    {
        return Organizer::forceCreate([
            'name' => $name,
            'account_id' => $accountId,
            'email' => 'organizer-'.$accountId.'@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'LIVE',
        ]);
    }

    private function seedOrganizerSettings(int $organizerId): int
    {
        return DB::table('organizer_settings')->insertGetId([
            'organizer_id' => $organizerId,
            'homepage_sections' => json_encode([
                ['id' => 'events', 'type' => 'EVENTS', 'enabled' => true],
                ['id' => 'travel', 'type' => 'TEXT', 'enabled' => true, 'body' => '<p>Lost? call Jane on 555-0100</p>'],
                ['id' => 'faq', 'type' => 'FAQ', 'enabled' => true, 'faqs' => [
                    ['question' => 'Who runs this?', 'answer' => '<p>Jane Doe, jane@example.com</p>'],
                ]],
            ]),
            'website_url' => 'https://jane.example.com',
            'location_details' => json_encode(['address_line_1' => '1 Jane Street', 'city' => 'Dublin']),
            'social_media_handles' => json_encode(['instagram' => 'janedoe']),
            'seo_title' => 'Jane Doe Events',
            'seo_description' => 'Events run by Jane Doe from her home in Dublin',
            'seo_keywords' => 'jane doe, dublin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
