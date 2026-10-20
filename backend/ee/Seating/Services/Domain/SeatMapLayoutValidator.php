<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Domain;

use HiEvents\Enterprise\Seating\Exceptions\InvalidSeatMapLayoutException;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\ValidatedSeatMapLayoutDTO;
use Illuminate\Config\Repository;

class SeatMapLayoutValidator
{
    public const SCHEMA_VERSION = 1;

    private const MAX_LAYOUT_BYTES = 1_500_000;

    public const MAX_BANDS = 20;

    private const MAX_AREAS = 20;

    private const MAX_ELEMENTS_PER_AREA = 1000;

    private const BAND_KEY_PATTERN = '/^b_[a-z0-9_]{1,20}$/';

    private const ID_PATTERN = '/^[a-z][a-z0-9]{0,11}$/';

    private const OVERRIDE_KEY_PATTERN = '/^\d{1,3}\.\d{1,3}$/';

    private const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    private const ROW_INDEX_PATTERN = '/^\d{1,3}$/';

    private const ROW_LABEL_PATTERN = '/^[A-Za-z0-9]{1,3}$/';

    private const MAX_ROW_LABELS = 100;

    private const CONTROL_CHARACTERS_PATTERN = '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}]+/u';

    private const ROW_FIELDS = [
        'name' => 'text:50',
        'section' => '?text:20',
        'spacing' => 'number:8:200',
        'curve' => 'number:-400:400',
        'aisles' => 'ints:1:200',
        'aisleWidth' => '?number:0:400',
        'rowLabelStyle' => 'enum:alpha,alphaSkipIO,num,none',
        'startRow' => 'text:3',
        'startSeat' => 'int:0:9999',
        'direction' => '?enum:ltr,rtl',
    ];

    private const ROW_NUMBERING = 'seq,odd,oddOnly,evenOnly';

    private const ELEMENT_FIELDS = [
        'row' => self::ROW_FIELDS + ['count' => 'int:1:200', 'numbering' => 'enum:'.self::ROW_NUMBERING],
        'block' => self::ROW_FIELDS + [
            'rows' => 'int:1:100',
            'cols' => 'int:1:200',
            'rowSpacing' => 'number:8:200',
            'taper' => 'int:-10:10',
            'numbering' => 'enum:'.self::ROW_NUMBERING.',cont',
        ],
        'table' => [
            'shape' => 'enum:round,rect',
            'label' => 'text:20',
            'chairs' => 'int:2:40',
            'd' => 'number:20:600',
            'w' => 'number:20:1000',
            'h' => 'number:20:1000',
            'gap' => 'number:0:100',
        ],
        'zone' => ['label' => 'text:50', 'capacity' => 'int:1:10000'],
        'object' => [
            'kind' => 'enum:stage,floor,bar,entrance,pillar,wall',
            'w' => 'number:1:5000',
            'h' => 'number:1:5000',
            'label' => 'text:50:empty',
        ],
        'label' => ['text' => 'text:80', 'size' => 'number:6:120'],
    ];

    private const SEATED_TYPES = ['row', 'block', 'table'];

    private array $errors = [];

    private array $bandKeys = [];

    private array $elementIds = [];

    private int $seatCount = 0;

    public function __construct(private readonly Repository $config) {}

    /**
     * @throws InvalidSeatMapLayoutException
     */
    public function validate(array $layout): ValidatedSeatMapLayoutDTO
    {
        $this->errors = [];
        $this->bandKeys = [];
        $this->elementIds = [];
        $this->seatCount = 0;

        if (($layout['schema'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidSeatMapLayoutException(['schema' => __('Unsupported seat map schema version')]);
        }

        if (strlen(json_encode($layout, JSON_THROW_ON_ERROR)) > self::MAX_LAYOUT_BYTES) {
            throw new InvalidSeatMapLayoutException(['layout' => __('The seat map is too large')]);
        }

        $canonical = [
            'schema' => self::SCHEMA_VERSION,
            'bands' => $this->bands($layout['bands'] ?? null),
            'areas' => $this->areas($layout['areas'] ?? null),
        ];

        $maxSeats = (int) $this->config->get('app.seat_map_max_seats', 3000);
        if ($this->seatCount > $maxSeats) {
            $this->errors['areas'] = __('A seat map can have at most :max seats', ['max' => $maxSeats]);
        }

        if ($this->errors !== []) {
            throw new InvalidSeatMapLayoutException($this->errors);
        }

        return new ValidatedSeatMapLayoutDTO(layout: $canonical, seat_count: $this->seatCount);
    }

    private function bands(mixed $bands): array
    {
        if (! is_array($bands) || $bands === [] || count($bands) > self::MAX_BANDS) {
            $this->errors['bands'] = __('A seat map needs between 1 and :max bands', ['max' => self::MAX_BANDS]);

            return [];
        }

        $canonical = [];
        foreach (array_values($bands) as $index => $band) {
            $path = "bands.$index";
            $key = $band['key'] ?? null;
            if (! is_string($key) || ! preg_match(self::BAND_KEY_PATTERN, $key) || in_array($key, $this->bandKeys($canonical), true)) {
                $this->errors["$path.key"] = __('Band keys must be valid and unique');

                continue;
            }
            $canonical[] = [
                'key' => $key,
                'name' => $this->field($band, 'name', 'text:50', $path),
                'color' => $this->matching($band['color'] ?? null, self::COLOR_PATTERN, "$path.color"),
            ];
        }

        $this->bandKeys = $this->bandKeys($canonical);

        return $canonical;
    }

    private function bandKeys(array $bands): array
    {
        return array_column($bands, 'key');
    }

    private function areas(mixed $areas): array
    {
        if (! is_array($areas) || $areas === [] || count($areas) > self::MAX_AREAS) {
            $this->errors['areas'] = __('A seat map needs between 1 and :max areas', ['max' => self::MAX_AREAS]);

            return [];
        }

        $canonical = [];
        $names = [];
        foreach (array_values($areas) as $index => $area) {
            $path = "areas.$index";
            $name = $this->field($area, 'name', 'text:50', $path);
            if (in_array(mb_strtolower((string) $name), $names, true)) {
                $this->errors["$path.name"] = __('Area names must be unique');
            }
            $names[] = mb_strtolower((string) $name);

            $canonical[] = [
                'id' => $this->uniqueId($area['id'] ?? null, "$path.id"),
                'name' => $name,
                'level' => $this->field($area, 'level', 'int:-5:50', $path),
                'focal' => $this->focal($area['focal'] ?? null, $path),
                'elements' => $this->elements($area['elements'] ?? null, $path),
            ];
        }

        return $canonical;
    }

    private function focal(mixed $focal, string $path): ?array
    {
        if ($focal === null) {
            return null;
        }

        return [
            'x' => $this->field($focal, 'x', 'number', "$path.focal"),
            'y' => $this->field($focal, 'y', 'number', "$path.focal"),
        ];
    }

    private function elements(mixed $elements, string $areaPath): array
    {
        if (! is_array($elements) || count($elements) > self::MAX_ELEMENTS_PER_AREA) {
            $this->errors["$areaPath.elements"] = __('An area can have at most :max elements', ['max' => self::MAX_ELEMENTS_PER_AREA]);

            return [];
        }

        $canonical = [];
        $labels = [];
        foreach (array_values($elements) as $index => $element) {
            $path = "$areaPath.elements.$index";
            $type = $element['type'] ?? null;
            if (! is_string($type) || ! isset(self::ELEMENT_FIELDS[$type])) {
                $this->errors["$path.type"] = __('Unknown element type');

                continue;
            }

            $canonicalElement = $this->element($element, $type, $path);
            foreach ($canonicalElement['seats'] ?? [] as $seat) {
                if (isset($labels[$seat['label']])) {
                    $this->errors["$path.seats"] = __('Seat :label appears more than once in this area', ['label' => $seat['label']]);
                }
                $labels[$seat['label']] = true;
            }
            $canonical[] = $canonicalElement;
        }

        return $canonical;
    }

    private function element(array $element, string $type, string $path): array
    {
        $canonical = ['id' => $this->uniqueId($element['id'] ?? null, "$path.id"), 'type' => $type];

        if ($type === 'zone') {
            $canonical['pts'] = $this->points($element['pts'] ?? null, "$path.pts");
        } else {
            $canonical['x'] = $this->field($element, 'x', 'number', $path);
            $canonical['y'] = $this->field($element, 'y', 'number', $path);
            $canonical['rotation'] = $this->field($element, 'rotation', 'number:-360:360', $path);
        }

        foreach (self::ELEMENT_FIELDS[$type] as $key => $rule) {
            $value = $this->field($element, $key, $rule, $path);
            if ($value !== null || ! str_starts_with($rule, '?')) {
                $canonical[$key] = $value;
            }
        }

        if (($type === 'row' || $type === 'block') && ! empty($element['rowLabels'] ?? null)) {
            $canonical['rowLabels'] = $this->rowLabels($element['rowLabels'], "$path.rowLabels");
        }

        if ($type === 'zone' || in_array($type, self::SEATED_TYPES, true)) {
            $canonical['band'] = $this->band($element['band'] ?? null, "$path.band");
        }

        if (in_array($type, self::SEATED_TYPES, true)) {
            $canonical['seatSize'] = $this->field($element, 'seatSize', 'number:6:60', $path);
            $canonical['overrides'] = $this->overrides($element['overrides'] ?? [], $path);
            $canonical['seats'] = $this->seats(
                $element['seats'] ?? null,
                (string) $canonical['id'],
                $canonical['band'],
                $canonical['overrides'],
                $path,
            );
        }

        return $canonical;
    }

    private function overrides(mixed $overrides, string $path): array
    {
        if (! is_array($overrides)) {
            $this->errors["$path.overrides"] = __('Invalid seat overrides');

            return [];
        }

        $canonical = [];
        foreach ($overrides as $key => $override) {
            $overridePath = "$path.overrides.$key";
            if (! preg_match(self::OVERRIDE_KEY_PATTERN, (string) $key) || ! is_array($override)) {
                $this->errors[$overridePath] = __('Invalid seat override');

                continue;
            }
            $canonical[(string) $key] = array_filter([
                'band' => isset($override['band']) ? $this->band($override['band'], "$overridePath.band") : null,
                'acc' => ($override['acc'] ?? false) === true ? true : null,
                'comp' => ($override['comp'] ?? false) === true ? true : null,
                'note' => $this->field($override, 'note', '?text:255', $overridePath),
                'removed' => ($override['removed'] ?? false) === true ? true : null,
            ], static fn ($value) => $value !== null);

            if (isset($canonical[(string) $key]['acc'], $canonical[(string) $key]['comp'])) {
                $this->errors[$overridePath] = __('A seat cannot be both a wheelchair space and a companion seat');
            }
        }

        return $canonical;
    }

    private function seats(mixed $seats, string $elementId, ?string $elementBand, array $overrides, string $path): array
    {
        if (! is_array($seats)) {
            $this->errors["$path.seats"] = __('Seats are missing');

            return [];
        }

        $canonical = [];
        $uidPattern = '/^'.preg_quote($elementId, '/').'\.\d{1,3}\.\d{1,3}$/';
        foreach (array_values($seats) as $index => $seat) {
            $seatPath = "$path.seats.$index";
            $uid = $this->matching($seat['uid'] ?? null, $uidPattern, "$seatPath.uid");
            if ($uid !== null && isset($canonical[$uid])) {
                $this->errors["$seatPath.uid"] = __('Seat identifiers must be unique');
            }
            $canonical[$uid ?? "invalid.$index"] = [
                'uid' => $uid,
                'row' => $this->field($seat, 'row', 'int:0:999', $seatPath),
                'n' => $this->field($seat, 'n', 'text:8', $seatPath),
                'label' => $this->field($seat, 'label', 'text:60', $seatPath),
                'x' => $this->field($seat, 'x', 'number', $seatPath),
                'y' => $this->field($seat, 'y', 'number', $seatPath),
                'a' => $this->field($seat, 'a', 'number', $seatPath),
                'band' => $this->band($seat['band'] ?? null, "$seatPath.band"),
                'acc' => ($seat['acc'] ?? false) === true,
                'comp' => ($seat['comp'] ?? false) === true,
                'note' => $this->field($seat, 'note', '?text:255', $seatPath),
                'gapAfter' => ($seat['gapAfter'] ?? false) === true,
            ];

            if (($seat['acc'] ?? false) === true && ($seat['comp'] ?? false) === true) {
                $this->errors["$seatPath.comp"] = __('A seat cannot be both a wheelchair space and a companion seat');
            }

            $this->assertSeatMatchesLayout($canonical[$uid ?? "invalid.$index"], $elementId, $elementBand, $overrides, $seatPath);
        }

        $this->seatCount += count($canonical);

        return array_values($canonical);
    }

    private function assertSeatMatchesLayout(array $seat, string $elementId, ?string $elementBand, array $overrides, string $seatPath): void
    {
        if ($seat['uid'] === null) {
            return;
        }

        $overrideKey = substr($seat['uid'], strlen($elementId) + 1);
        $override = $overrides[$overrideKey] ?? [];
        $label = $seat['label'] ?? $seat['uid'];

        if (($override['removed'] ?? false) === true) {
            $this->errors["$seatPath.uid"] = __('Seat :label has been removed from the map', ['label' => $label]);
        }

        if ($seat['row'] !== null && (int) explode('.', $overrideKey)[0] !== (int) $seat['row']) {
            $this->errors["$seatPath.row"] = __('Seat :label is in the wrong row', ['label' => $label]);
        }

        if ($seat['acc'] !== ($override['acc'] ?? false) || $seat['comp'] !== ($override['comp'] ?? false)) {
            $this->errors["$seatPath.acc"] = __('Seat :label does not match the accessibility set for it on the map', ['label' => $label]);
        }

        $expectedBand = $override['band'] ?? $elementBand;

        if ($seat['band'] !== null && $expectedBand !== null && $seat['band'] !== $expectedBand) {
            $this->errors["$seatPath.band"] = __('Seat :label does not match the band set for it on the map', ['label' => $label]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function rowLabels(mixed $rowLabels, string $path): array
    {
        if (! is_array($rowLabels) || count($rowLabels) > self::MAX_ROW_LABELS) {
            $this->errors[$path] = __('Invalid row labels');

            return [];
        }

        $canonical = [];
        foreach ($rowLabels as $rowIndex => $label) {
            if (! preg_match(self::ROW_INDEX_PATTERN, (string) $rowIndex) || ! is_string($label) || ! preg_match(self::ROW_LABEL_PATTERN, $label)) {
                $this->errors["$path.$rowIndex"] = __('Row labels must be 1 to 3 letters or digits');

                continue;
            }
            $canonical[(int) $rowIndex] = $label;
        }

        return $canonical;
    }

    private function points(mixed $points, string $path): array
    {
        if (! is_array($points) || count($points) < 3 || count($points) > 50) {
            $this->errors[$path] = __('A zone needs between 3 and 50 points');

            return [];
        }

        return array_map(fn ($point) => [
            $this->field((array) $point, 0, 'number', $path),
            $this->field((array) $point, 1, 'number', $path),
        ], array_values($points));
    }

    private function band(mixed $key, string $path): ?string
    {
        if (! is_string($key) || ! in_array($key, $this->bandKeys, true)) {
            $this->errors[$path] = __('Unknown band');

            return null;
        }

        return $key;
    }

    private function uniqueId(mixed $id, string $path): ?string
    {
        $id = $this->matching($id, self::ID_PATTERN, $path);
        if ($id !== null && isset($this->elementIds[$id])) {
            $this->errors[$path] = __('Identifiers must be unique');
        }
        $this->elementIds[(string) $id] = true;

        return $id;
    }

    private function matching(mixed $value, string $pattern, string $path): ?string
    {
        if (! is_string($value) || ! preg_match($pattern, $value)) {
            $this->errors[$path] = __('Invalid value');

            return null;
        }

        return $value;
    }

    private function field(mixed $source, string|int $key, string $rule, string $path): mixed
    {
        $optional = str_starts_with($rule, '?');
        [$type, $first, $second] = array_pad(explode(':', ltrim($rule, '?')), 3, null);
        $value = is_array($source) ? ($source[$key] ?? null) : null;

        if ($value === null && $optional) {
            return null;
        }

        $result = match ($type) {
            'text' => $this->text($second === 'empty' ? (string) $value : $value, (int) $first, $second === 'empty'),
            'number' => $this->number($value, $first, $second),
            'int' => is_int($value) ? $this->number($value, $first, $second) : null,
            'ints' => $this->ints($value, (int) $first, (int) $second),
            'enum' => in_array($value, explode(',', (string) $first), true) ? $value : null,
        };

        if ($result === null) {
            $this->errors["$path.$key"] = __('Invalid value');
        }

        return $result;
    }

    private function text(mixed $value, int $maxLength, bool $allowEmpty): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace(self::CONTROL_CHARACTERS_PATTERN, ' ', strip_tags($value)) ?? '');
        if (($clean === '' && ! $allowEmpty) || mb_strlen($clean) > $maxLength) {
            return null;
        }

        return $clean;
    }

    private function number(mixed $value, ?string $min, ?string $max): int|float|null
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
            return null;
        }

        $min ??= '-100000';
        $max ??= '100000';

        return $value >= (float) $min && $value <= (float) $max ? $value : null;
    }

    private function ints(mixed $value, int $min, int $max): ?array
    {
        if (! is_array($value) || count($value) > 50) {
            return null;
        }

        foreach ($value as $item) {
            if (! is_int($item) || $item < $min || $item > $max) {
                return null;
            }
        }

        return array_values($value);
    }
}
