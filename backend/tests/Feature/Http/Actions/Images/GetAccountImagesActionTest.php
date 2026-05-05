<?php

namespace Tests\Feature\Http\Actions\Images;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\Image;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GetAccountImagesActionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private int $accountId;
    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => [
                'percentage' => 1.5,
                'fixed' => 0,
            ],
        ]);

        $password = 'password123';
        $this->user = User::factory()->password($password)->withAccount()->create();
        $this->accountId = $this->user->accounts()->first()->id;

        $loginResponse = $this->postJson('/auth/login', [
            'email' => $this->user->email,
            'password' => $password,
        ]);

        $this->authToken = $loginResponse->headers->get('X-Auth-Token');
    }

    public function test_returns_paginated_response_when_no_images(): void
    {
        $response = $this->getJson('/images', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_only_current_account_images(): void
    {
        $this->createImage($this->accountId, 'mine.png');
        $this->createImage($this->accountId, 'mine-too.png');

        $otherUser = User::factory()->password('x')->withAccount()->create();
        $otherAccountId = $otherUser->accounts()->first()->id;
        $this->createImage($otherAccountId, 'theirs.png');

        $response = $this->getJson('/images', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $filenames = collect($response->json('data'))->pluck('file_name')->all();
        $this->assertContains('mine.png', $filenames);
        $this->assertContains('mine-too.png', $filenames);
        $this->assertNotContains('theirs.png', $filenames);
    }

    public function test_filters_by_filename_search_query(): void
    {
        $this->createImage($this->accountId, 'banner-summer.png');
        $this->createImage($this->accountId, 'banner-winter.png');
        $this->createImage($this->accountId, 'logo.png');

        $response = $this->getJson('/images?query=banner', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(2, 'data');

        $filenames = collect($response->json('data'))->pluck('file_name')->all();
        $this->assertNotContains('logo.png', $filenames);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/images');

        $this->assertNotEquals(ResponseCodes::HTTP_OK, $response->status());
    }

    private function createImage(int $accountId, string $filename): Image
    {
        return Image::create([
            'account_id' => $accountId,
            'entity_id' => null,
            'entity_type' => null,
            'type' => 'EVENT_COVER',
            'filename' => $filename,
            'disk' => 'public',
            'path' => 'images/' . $filename,
            'size' => 1024,
            'mime_type' => 'image/png',
        ]);
    }
}
