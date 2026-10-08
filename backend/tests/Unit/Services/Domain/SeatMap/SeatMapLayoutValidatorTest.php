<?php

namespace Tests\Unit\Services\Domain\SeatMap;

use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeatMapLayoutValidatorTest extends TestCase
{
    public static function templateProvider(): array
    {
        return [
            'theatre' => ['theatre', 402],
            'banquet' => ['banquet', 112],
            'club' => ['club', 48],
            'conference' => ['conference', 182],
            'thrust' => ['thrust', 193],
            'empty' => ['empty', 0],
        ];
    }

    #[DataProvider('templateProvider')]
    public function test_generated_templates_are_valid_and_survive_canonicalisation_unchanged(string $template, int $seatCount): void
    {
        $layout = $this->fixture($template);

        $result = $this->validator()->validate($layout);

        $this->assertSame($seatCount, $result->seat_count);
        $this->assertEquals($layout, $result->layout);
    }

    public function test_unknown_keys_are_dropped(): void
    {
        $layout = $this->fixture('empty');
        $layout['injected'] = 'value';
        $layout['areas'][0]['elements'][0]['onclick'] = 'alert(1)';

        $result = $this->validator()->validate($layout);

        $this->assertArrayNotHasKey('injected', $result->layout);
        $this->assertArrayNotHasKey('onclick', $result->layout['areas'][0]['elements'][0]);
    }

    public function test_markup_is_stripped_from_text(): void
    {
        $layout = $this->fixture('empty');
        $layout['areas'][0]['name'] = '<b>Main</b> hall';

        $result = $this->validator()->validate($layout);

        $this->assertSame('Main hall', $result->layout['areas'][0]['name']);
    }

    public function test_empty_object_label_nulled_by_http_middleware_is_accepted(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][6]['label'] = null;

        $result = $this->validator()->validate($layout);

        $this->assertSame('', $result->layout['areas'][0]['elements'][6]['label']);
    }

    public function test_unsupported_schema_is_rejected(): void
    {
        $layout = $this->fixture('empty');
        $layout['schema'] = 2;

        $this->assertInvalid($layout, 'schema');
    }

    public function test_seat_referencing_unknown_band_is_rejected(): void
    {
        $layout = $this->fixture('conference');
        $layout['areas'][0]['elements'][1]['seats'][0]['band'] = 'b_missing';

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.band');
    }

    public function test_seat_band_must_match_its_element_band(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['seats'][0]['band'] = 'b_standard';

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.band');
    }

    public function test_seat_band_follows_its_override(): void
    {
        $layout = $this->fixture('theatre');
        $element = &$layout['areas'][0]['elements'][1];
        $seatKey = substr($element['seats'][0]['uid'], strlen($element['id']) + 1);
        $element['overrides'][$seatKey] = ['band' => 'b_value'];
        $element['seats'][0]['band'] = 'b_value';

        $this->assertSame(402, $this->validator()->validate($layout)->seat_count);

        $element['seats'][0]['band'] = 'b_premium';

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.band');
    }

    public function test_a_seat_the_map_marks_as_removed_is_rejected(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['overrides']['0.0'] = ['removed' => true];

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.uid');
    }

    public function test_a_seat_must_sit_in_the_row_its_uid_names(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['seats'][0]['row'] = 1;

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.row');
    }

    public function test_seat_accessibility_must_match_its_override(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['seats'][0]['acc'] = true;

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.acc');

        $layout['areas'][0]['elements'][1]['overrides']['0.0'] = ['acc' => true];

        $this->assertSame(402, $this->validator()->validate($layout)->seat_count);
    }

    public function test_duplicate_seat_label_within_an_area_is_rejected(): void
    {
        $layout = $this->fixture('conference');
        $layout['areas'][0]['elements'][2]['startSeat'] = 1;
        foreach ($layout['areas'][0]['elements'][2]['seats'] as $index => $seat) {
            $layout['areas'][0]['elements'][2]['seats'][$index]['label'] = $layout['areas'][0]['elements'][1]['seats'][$index]['label'];
        }

        $this->assertInvalid($layout, 'areas.0.elements.2.seats');
    }

    public function test_seat_uid_must_belong_to_its_element(): void
    {
        $layout = $this->fixture('conference');
        $layout['areas'][0]['elements'][1]['seats'][0]['uid'] = 'e99.0.0';

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.uid');
    }

    public function test_duplicate_seat_uid_is_rejected(): void
    {
        $layout = $this->fixture('conference');
        $seats = &$layout['areas'][0]['elements'][1]['seats'];
        $seats[1]['uid'] = $seats[0]['uid'];

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.1.uid');
    }

    public function test_duplicate_element_id_across_areas_is_rejected(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][1]['elements'][0]['id'] = $layout['areas'][0]['elements'][0]['id'];

        $this->assertInvalid($layout, 'areas.1.elements.0.id');
    }

    public function test_duplicate_area_names_are_rejected(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][1]['name'] = 'stalls';

        $this->assertInvalid($layout, 'areas.1.name');
    }

    public function test_zone_capacity_must_be_positive(): void
    {
        $layout = $this->fixture('club');
        $layout['areas'][0]['elements'][1]['capacity'] = 0;

        $this->assertInvalid($layout, 'areas.0.elements.1.capacity');
    }

    public function test_unknown_element_type_is_rejected(): void
    {
        $layout = $this->fixture('empty');
        $layout['areas'][0]['elements'][0]['type'] = 'script';

        $this->assertInvalid($layout, 'areas.0.elements.0.type');
    }

    public function test_seat_limit_is_enforced(): void
    {
        $this->assertInvalid($this->fixture('banquet'), 'areas', maxSeats: 100);
    }

    public static function numberingCaseProvider(): array
    {
        $cases = json_decode(
            file_get_contents(__DIR__.'/../../../../Fixtures/seating/numbering-cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return collect($cases)->mapWithKeys(fn (array $case) => [$case['name'] => [$case]])->all();
    }

    #[DataProvider('numberingCaseProvider')]
    public function test_every_numbering_scheme_in_the_shared_fixture_is_accepted(array $case): void
    {
        $element = $case['element'];
        $element['seats'] = array_map(fn (array $seat) => [
            'uid' => $seat['uid'],
            'row' => (int) explode('.', $seat['uid'])[1],
            'n' => $seat['n'],
            'label' => $seat['label'],
            'x' => 0,
            'y' => 0,
            'a' => 0,
            'band' => $element['band'],
            'acc' => false,
            'comp' => false,
            'note' => null,
            'gapAfter' => false,
        ], $case['expected']);
        $layout = $this->fixture('empty');
        $layout['areas'][0]['elements'] = [$element];

        $result = $this->validator()->validate($layout);

        $this->assertSame(count($case['expected']), $result->seat_count);
        $this->assertSame($element['numbering'], $result->layout['areas'][0]['elements'][0]['numbering']);
        $this->assertSame($element['direction'] ?? null, $result->layout['areas'][0]['elements'][0]['direction'] ?? null);
        $this->assertEquals($element['rowLabels'] ?? null, $result->layout['areas'][0]['elements'][0]['rowLabels'] ?? null);
    }

    public function test_unknown_numbering_and_direction_are_rejected(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['numbering'] = 'reverse';
        $layout['areas'][0]['elements'][2]['direction'] = 'up';

        $this->assertInvalid($layout, 'areas.0.elements.1.numbering');
        $this->assertInvalid($layout, 'areas.0.elements.2.direction');
    }

    public function test_row_labels_must_be_short_letters_or_digits_keyed_by_row(): void
    {
        $layout = $this->fixture('theatre');
        $layout['areas'][0]['elements'][1]['rowLabels'] = ['1' => 'AB-C'];

        $this->assertInvalid($layout, 'areas.0.elements.1.rowLabels.1');

        $layout['areas'][0]['elements'][1]['rowLabels'] = ['first' => 'A'];

        $this->assertInvalid($layout, 'areas.0.elements.1.rowLabels.first');
    }

    public function test_a_seat_cannot_be_both_a_wheelchair_space_and_a_companion_seat(): void
    {
        $layout = $this->fixture('theatre');
        $element = &$layout['areas'][0]['elements'][1];
        $element['overrides']['0.0'] = ['acc' => true, 'comp' => true];

        $this->assertInvalid($layout, 'areas.0.elements.1.overrides.0.0');

        $element['overrides']['0.0'] = ['comp' => true];
        $element['seats'][0]['acc'] = true;
        $element['seats'][0]['comp'] = true;

        $this->assertInvalid($layout, 'areas.0.elements.1.seats.0.comp');
    }

    public function test_companion_seats_survive_canonicalisation(): void
    {
        $layout = $this->fixture('theatre');
        $element = &$layout['areas'][0]['elements'][1];
        $element['overrides']['0.0'] = ['comp' => true];
        $element['seats'][0]['comp'] = true;

        $result = $this->validator()->validate($layout);

        $this->assertSame(['comp' => true], $result->layout['areas'][0]['elements'][1]['overrides']['0.0']);
        $this->assertTrue($result->layout['areas'][0]['elements'][1]['seats'][0]['comp']);
        $this->assertFalse($result->layout['areas'][0]['elements'][1]['seats'][1]['comp']);
    }

    private function assertInvalid(array $layout, string $expectedErrorPath, int $maxSeats = 3000): void
    {
        try {
            $this->validator($maxSeats)->validate($layout);
            $this->fail('Expected the layout to be rejected');
        } catch (InvalidSeatMapLayoutException $exception) {
            $this->assertArrayHasKey($expectedErrorPath, $exception->getErrors());
        }
    }

    private function validator(int $maxSeats = 3000): SeatMapLayoutValidator
    {
        return new SeatMapLayoutValidator(new Repository(['app' => ['seat_map_max_seats' => $maxSeats]]));
    }

    private function fixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
