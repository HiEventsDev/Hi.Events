<?php

namespace Tests\Feature\Http\Actions\EventSettings;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class HomepageSectionValidationMessagesTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const INVALID_SECTION = "Something in your homepage sections couldn't be saved. Reload the page and try again.";

    public static function invalidSections(): array
    {
        $faq = fn (array $faqs) => [['id' => 'faq', 'type' => 'FAQ', 'faqs' => $faqs]];
        $sponsors = fn (array $sponsors) => [['id' => 'thanks', 'type' => 'SPONSORS', 'sponsors' => $sponsors]];
        $sponsor = fn (array $overrides) => $sponsors([[...['image_id' => 1, 'name' => 'Acme', 'url' => 'https://acme.test'], ...$overrides]]);

        return [
            'too many sections' => [
                array_map(fn (int $index) => ['id' => "text_$index", 'type' => 'TEXT'], range(1, 42)),
                'homepage_sections',
                'You can add up to 41 sections.',
            ],
            'sections not a list' => ['nope', 'homepage_sections', self::INVALID_SECTION],
            'malformed section id' => [[['id' => 'bad id!', 'type' => 'TEXT']], 'homepage_sections.0.id', self::INVALID_SECTION],
            'missing section id' => [[['type' => 'TEXT']], 'homepage_sections.0.id', self::INVALID_SECTION],
            'unsupported section type' => [[['id' => 'x', 'type' => 'EVENTS']], 'homepage_sections.0.type', self::INVALID_SECTION],
            'non-boolean enabled' => [[['id' => 'x', 'type' => 'TEXT', 'enabled' => 'sometimes']], 'homepage_sections.0.enabled', self::INVALID_SECTION],
            'heading too long' => [
                [['id' => 'x', 'type' => 'TEXT', 'heading' => str_repeat('h', 101)]],
                'homepage_sections.0.heading',
                'Section headings can be up to 100 characters.',
            ],
            'text too long' => [
                [['id' => 'x', 'type' => 'TEXT', 'body' => str_repeat('b', 50001)]],
                'homepage_sections.0.body',
                'Text sections can be up to 50000 characters.',
            ],
            'too many questions' => [
                $faq(array_fill(0, 51, ['question' => 'Parking?', 'answer' => '<p>Yes</p>'])),
                'homepage_sections.0.faqs',
                'An FAQ section can have up to 50 questions.',
            ],
            'missing question' => [
                $faq([['question' => '', 'answer' => '<p>Yes</p>']]),
                'homepage_sections.0.faqs.0.question',
                'Every FAQ needs a question.',
            ],
            'question too long' => [
                $faq([['question' => str_repeat('q', 301), 'answer' => '<p>Yes</p>']]),
                'homepage_sections.0.faqs.0.question',
                'FAQ questions can be up to 300 characters.',
            ],
            'answer too long' => [
                $faq([['question' => 'Parking?', 'answer' => '<p>'.str_repeat('a', 5000).'</p>']]),
                'homepage_sections.0.faqs.0.answer',
                'FAQ answers can be up to 5000 characters.',
            ],
            'video link not https' => [
                [['id' => 'v', 'type' => 'VIDEO', 'video_url' => 'http://youtube.com/watch?v=abc']],
                'homepage_sections.0.video_url',
                'Use a YouTube or Vimeo link starting with https://',
            ],
            'video link too long' => [
                [['id' => 'v', 'type' => 'VIDEO', 'video_url' => 'https://youtube.com/watch?v='.str_repeat('a', 480)]],
                'homepage_sections.0.video_url',
                'Video links can be up to 500 characters.',
            ],
            'too many photos' => [
                [['id' => 'photos', 'type' => 'GALLERY', 'image_ids' => range(1, 41)]],
                'homepage_sections.0.image_ids',
                'A gallery can have up to 40 photos.',
            ],
            'photo id not a number' => [
                [['id' => 'photos', 'type' => 'GALLERY', 'image_ids' => ['first']]],
                'homepage_sections.0.image_ids.0',
                self::INVALID_SECTION,
            ],
            'too many sponsors' => [
                $sponsors(array_map(fn (int $id) => ['image_id' => $id], range(1, 61))),
                'homepage_sections.0.sponsors',
                'You can add up to 60 sponsors.',
            ],
            'sponsor without a logo' => [
                $sponsors([['name' => 'Acme']]),
                'homepage_sections.0.sponsors.0.image_id',
                'Every sponsor needs a logo.',
            ],
            'sponsor name too long' => [
                $sponsor(['name' => str_repeat('n', 101)]),
                'homepage_sections.0.sponsors.0.name',
                'Sponsor names can be up to 100 characters.',
            ],
            'sponsor link not https' => [
                $sponsor(['url' => 'acme.test']),
                'homepage_sections.0.sponsors.0.url',
                'Sponsor links must start with https://',
            ],
            'sponsor link too long' => [
                $sponsor(['url' => 'https://acme.test/'.str_repeat('a', 490)]),
                'homepage_sections.0.sponsors.0.url',
                'Sponsor links can be up to 500 characters.',
            ],
        ];
    }

    #[DataProvider('invalidSections')]
    public function test_event_homepage_section_errors_are_readable(mixed $sections, string $field, string $message): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);
        $headers = $this->headersFor($owner, $accountId);
        $eventId = $this->postJson('/events', [
            'title' => 'Sections test',
            'type' => 'SINGLE',
            'organizer_id' => $organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $headers)->assertSuccessful()->json('data.id');

        $errors = $this->patchJson("/events/$eventId/settings", ['homepage_sections' => $sections], $headers)
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame($message, $errors[$field][0] ?? null, json_encode($errors));
        $this->assertStringNotContainsString('homepage_sections', json_encode(array_values($errors)));
    }

    public function test_organizer_homepage_section_errors_are_readable(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        $errors = $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [
                ['id' => 'faq', 'type' => 'FAQ', 'faqs' => [['question' => 'Parking?', 'answer' => str_repeat('a', 5001)]]],
                ['id' => 'tickets', 'type' => 'TICKETS'],
            ],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('FAQ answers can be up to 5000 characters.', $errors['homepage_sections.0.faqs.0.answer'][0] ?? null);
        $this->assertSame(self::INVALID_SECTION, $errors['homepage_sections.1.type'][0] ?? null);
    }
}
