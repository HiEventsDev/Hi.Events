<?php

namespace Tests\Feature\Http\Actions\Images;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\Image;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    public function test_blocks_deletion_when_image_referenced_by_active_event(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);
        Storage::disk($disk)->put('generic/active-logo.png', 'fake-bytes');

        $image = $this->createImage($this->accountId, 'active-logo.png', $disk, 'generic/active-logo.png');
        $this->insertEvent('Live Brunch', 'description', '<img src="https://cdn/generic/active-logo.png">', '+10 days', '+10 days +2 hours');

        $response = $this->deleteJson('/images/'.$image->id, [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $response->assertJsonPath('has_protected_references', true);
        $response->assertJsonPath('deletable_with_confirm', false);
        $response->assertJsonStructure(['message', 'references' => [['entity_type', 'entity_id', 'entity_name', 'field_label', 'is_protected']]]);

        Storage::disk($disk)->assertExists('generic/active-logo.png');
        $this->assertDatabaseHas('images', ['id' => $image->id, 'deleted_at' => null]);
    }

    public function test_requires_confirm_when_image_referenced_only_by_ended_event(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);
        Storage::disk($disk)->put('generic/old-logo.png', 'fake-bytes');

        $image = $this->createImage($this->accountId, 'old-logo.png', $disk, 'generic/old-logo.png');
        $this->insertEvent('Past Rally', 'description', '<img src="https://cdn/generic/old-logo.png">', '-30 days', '-29 days');

        $response = $this->deleteJson('/images/'.$image->id, [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_CONFLICT);
        $response->assertJsonPath('has_protected_references', false);
        $response->assertJsonPath('deletable_with_confirm', true);

        Storage::disk($disk)->assertExists('generic/old-logo.png');
        $this->assertDatabaseHas('images', ['id' => $image->id, 'deleted_at' => null]);
    }

    public function test_deletes_with_confirm_when_only_ended_event_references(): void
    {
        $disk = config('filesystems.public');
        Storage::fake($disk);
        Storage::disk($disk)->put('generic/forced.png', 'fake-bytes');

        $image = $this->createImage($this->accountId, 'forced.png', $disk, 'generic/forced.png');
        $this->insertEvent('Past Rally', 'description', '<img src="https://cdn/generic/forced.png">', '-30 days', '-29 days');

        $response = $this->deleteJson('/images/'.$image->id.'?confirm=true', [], [
            'Authorization' => 'Bearer '.$this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_NO_CONTENT);
        Storage::disk($disk)->assertMissing('generic/forced.png');
        $this->assertSoftDeleted('images', ['id' => $image->id]);
    }

    private function insertEvent(string $title, string $field, string $value, string $startOffset, string $endOffset): int
    {
        $now = now();

        return (int) DB::table('events')->insertGetId([
            'title' => $title,
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'short_id' => 'e_'.bin2hex(random_bytes(4)),
            'start_date' => $now->copy()->modify($startOffset),
            'end_date' => $now->copy()->modify($endOffset),
            $field => $value,
            'status' => 'LIVE',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
