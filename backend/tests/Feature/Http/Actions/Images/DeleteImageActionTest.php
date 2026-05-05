<?php

namespace Tests\Feature\Http\Actions\Images;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\Image;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteImageActionTest extends TestCase
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

    public function test_deletes_image_record_and_file(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);
        Storage::disk($disk)->put('generic/banner.png', 'fake-bytes');

        $image = $this->createImage($this->accountId, 'banner.png', $disk, 'generic/banner.png');

        $response = $this->deleteJson('/images/'.$image->id, [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_NO_CONTENT);

        Storage::disk($disk)->assertMissing('generic/banner.png');

        $this->assertSoftDeleted('images', ['id' => $image->id]);
    }

    public function test_returns_no_content_when_underlying_file_already_missing(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);

        $image = $this->createImage($this->accountId, 'gone.png', $disk, 'generic/gone.png');

        $response = $this->deleteJson('/images/'.$image->id, [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_NO_CONTENT);
        $this->assertSoftDeleted('images', ['id' => $image->id]);
    }

    public function test_rejects_deleting_image_from_another_account(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);
        Storage::disk($disk)->put('generic/theirs.png', 'fake-bytes');

        $otherUser = User::factory()->password('x')->withAccount()->create();
        $otherAccountId = $otherUser->accounts()->first()->id;
        $image = $this->createImage($otherAccountId, 'theirs.png', $disk, 'generic/theirs.png');

        $response = $this->deleteJson('/images/'.$image->id, [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $this->assertNotEquals(ResponseCodes::HTTP_NO_CONTENT, $response->status());

        Storage::disk($disk)->assertExists('generic/theirs.png');
        $this->assertDatabaseHas('images', ['id' => $image->id, 'deleted_at' => null]);
    }

    public function test_requires_authentication(): void
    {
        $image = $this->createImage($this->accountId, 'unauth.png', 'public', 'generic/unauth.png');

        $response = $this->deleteJson('/images/'.$image->id);

        $this->assertNotEquals(ResponseCodes::HTTP_NO_CONTENT, $response->status());
    }

    private function createImage(int $accountId, string $filename, string $disk, string $path): Image
    {
        return Image::create([
            'account_id' => $accountId,
            'entity_id' => null,
            'entity_type' => null,
            'type' => 'GENERIC',
            'filename' => $filename,
            'disk' => $disk,
            'path' => $path,
            'size' => 1024,
            'mime_type' => 'image/png',
        ]);
    }
}
