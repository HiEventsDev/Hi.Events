import {ActionIcon, Button, NumberInput, Select, TextInput, Tooltip} from "@mantine/core";
import {
    IconCopy,
    IconLayoutAlignBottom,
    IconLayoutAlignCenter,
    IconLayoutAlignLeft,
    IconLayoutAlignMiddle,
    IconLayoutAlignRight,
    IconLayoutAlignTop,
    IconTrash,
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useState} from "react";
import {
    BlockElement,
    GeneratedSeat,
    isSeatedElement,
    RowElement,
    SeatMapArea,
    SeatMapElement,
    SeatMapBand,
    SeatOverride,
} from "../lib/types.ts";
import {firstRowSeatNumbers} from "../lib/generateSeats.ts";
import {AlignEdge} from "./ops/elements.ts";
import classes from "./SeatMapDesigner.module.scss";

type Option = {value: string; label: string};

type Field =
    | {key: string; label: string; kind: 'text'; maxLength: number; optional: boolean}
    | {key: string; label: string; kind: 'number'; min: number; max: number}
    | {key: string; label: string; kind: 'select'; options: Option[]; fallback?: string}
    | {key: 'aisles'; label: string; kind: 'aisles'}
    | {key: 'band'; label: string; kind: 'band'};

const text = (key: string, label: string, maxLength: number, optional = false): Field => ({key, label, kind: 'text', maxLength, optional});
const number = (key: string, label: string, min: number, max: number): Field => ({key, label, kind: 'number', min, max});
const select = (key: string, label: string, options: Option[], fallback?: string): Field => ({key, label, kind: 'select', options, fallback});

const rowFields = (): Field[] => [
    text('section', t`Section name`, 20, true),
    number('spacing', t`Seat spacing`, 8, 200),
    number('curve', t`Curve`, -400, 400),
    {key: 'aisles', label: t`Aisles after seats`, kind: 'aisles'},
    select('rowLabelStyle', t`Row labels`, [
        {value: 'alpha', label: t`Letters`},
        {value: 'alphaSkipIO', label: t`Letters skipping I and O`},
        {value: 'num', label: t`Numbers`},
        {value: 'none', label: t`None`},
    ]),
    text('startRow', t`First row`, 3),
    number('startSeat', t`First seat number`, 0, 9999),
    select('direction', t`Number from`, [
        {value: 'ltr', label: t`Left`},
        {value: 'rtl', label: t`Right`},
    ], 'ltr'),
];

const fieldsFor = (element: SeatMapElement): Field[] => {
    const band: Field = {key: 'band', label: t`Band`, kind: 'band'};
    const rotation = number('rotation', t`Rotation`, -360, 360);
    const numbering = [
        {value: 'seq', label: t`Consecutive`},
        {value: 'odd', label: t`Odd and even from the centre`},
        {value: 'oddOnly', label: t`Odd numbers only`},
        {value: 'evenOnly', label: t`Even numbers only`},
    ];

    switch (element.type) {
        case 'row':
            return [text('name', t`Name`, 50), band, number('count', t`Seats`, 1, 200), ...rowFields(),
                select('numbering', t`Seat numbering`, numbering), rotation];
        case 'block':
            return [text('name', t`Name`, 50), band, number('rows', t`Rows`, 1, 100), number('cols', t`Seats per row`, 1, 200),
                number('rowSpacing', t`Row spacing`, 8, 200), number('taper', t`Extra seats per row`, -10, 10), ...rowFields(),
                select('numbering', t`Seat numbering`, [...numbering, {value: 'cont', label: t`Continuous across rows`}]), rotation];
        case 'table':
            return [text('label', t`Table name`, 20), band, number('chairs', t`Chairs`, 2, 40),
                select('shape', t`Shape`, [{value: 'round', label: t`Round`}, {value: 'rect', label: t`Rectangular`}]),
                ...(element.shape === 'round'
                    ? [number('d', t`Diameter`, 20, 600)]
                    : [number('w', t`Width`, 20, 1000), number('h', t`Depth`, 20, 1000)]),
                rotation];
        case 'zone':
            return [text('label', t`Name`, 50), band, number('capacity', t`Capacity`, 1, 10000)];
        case 'object':
            return [
                select('kind', t`Type`, [
                    {value: 'stage', label: t`Stage`},
                    {value: 'floor', label: t`Dance floor`},
                    {value: 'bar', label: t`Bar`},
                    {value: 'entrance', label: t`Entrance`},
                    {value: 'pillar', label: t`Pillar`},
                    {value: 'wall', label: t`Wall`},
                ]),
                text('label', t`Label`, 50), number('w', t`Width`, 1, 5000), number('h', t`Height`, 1, 5000), rotation];
        case 'label':
            return [text('text', t`Text`, 80), number('size', t`Size`, 6, 120), rotation];
    }
};

interface AislesInputProps {
    element: RowElement | BlockElement;
    label: string;
    onPreview: (patch: Record<string, unknown>) => void;
}

const AislesInput = ({element, label, onPreview}: AislesInputProps) => {
    const numbers = firstRowSeatNumbers(element);
    const gapPositions = numbers.length - 1;
    const outsideFirstRow = element.aisles.filter(position => position > gapPositions);
    const [value, setValue] = useState(() => element.aisles
        .filter(position => position <= gapPositions)
        .map(position => numbers[position - 1])
        .join(', '));
    const [unknownSeats, setUnknownSeats] = useState<string[]>([]);

    const change = (next: string) => {
        setValue(next);
        const parts = next.split(/[\s,]+/).filter(Boolean);
        const positions = parts.map(part => numbers.indexOf(Number(part)) + 1);
        const unknown = parts.filter((_, index) => positions[index] < 1 || positions[index] > gapPositions);
        setUnknownSeats(unknown);
        if (unknown.length === 0) {
            onPreview({aisles: [...new Set([...positions, ...outsideFirstRow])].sort((a, b) => a - b)});
        }
    };

    return (
        <TextInput size="xs" label={label} value={value} data-testid="seat-map-designer-field-aisles"
                   description={t`Seat numbers from the first row, separated by commas`}
                   error={unknownSeats.length > 0 && t`No aisle can go after seat ${unknownSeats.join(', ')} in the first row`}
                   onChange={event => change(event.currentTarget.value)}/>
    );
};

interface ElementInspectorProps {
    elements: SeatMapElement[];
    bands: SeatMapBand[];
    areas: SeatMapArea[];
    areaId: string;
    onPreview: (patch: Record<string, unknown>) => void;
    onApply: (patch: Record<string, unknown>) => void;
    onCommit: () => void;
    onAlign: (edge: AlignEdge) => void;
    onDuplicate: () => void;
    onDelete: () => void;
    onMoveToArea: (areaId: string) => void;
    onRestoreSeats: () => void;
}

const ALIGNMENTS: {edge: AlignEdge; Icon: typeof IconTrash; label: () => string}[] = [
    {edge: 'left', Icon: IconLayoutAlignLeft, label: () => t`Align left`},
    {edge: 'centerX', Icon: IconLayoutAlignCenter, label: () => t`Align centre`},
    {edge: 'right', Icon: IconLayoutAlignRight, label: () => t`Align right`},
    {edge: 'top', Icon: IconLayoutAlignTop, label: () => t`Align top`},
    {edge: 'centerY', Icon: IconLayoutAlignMiddle, label: () => t`Align middle`},
    {edge: 'bottom', Icon: IconLayoutAlignBottom, label: () => t`Align bottom`},
];

export const ElementInspector = ({
    elements,
    bands,
    areas,
    areaId,
    onPreview,
    onApply,
    onCommit,
    onAlign,
    onDuplicate,
    onDelete,
    onMoveToArea,
    onRestoreSeats,
}: ElementInspectorProps) => {
    const single = elements.length === 1 ? elements[0] : null;
    const values = single as unknown as Record<string, unknown>;
    const removedSeats = single && isSeatedElement(single)
        ? Object.values(single.overrides).filter(override => override.removed).length
        : 0;

    return (
        <section className={classes.panelSection} onBlur={onCommit}>
            <h3 className={classes.panelHeading}>
                {single ? t`Selected item` : t`${elements.length} items selected`}
            </h3>

            {single && fieldsFor(single).map(field => {
                if (field.kind === 'text') {
                    return <TextInput key={field.key} size="xs" label={field.label} maxLength={field.maxLength}
                                      value={(values[field.key] as string | undefined) ?? ''}
                                      error={!field.optional && field.key !== 'label' && !values[field.key]}
                                      onChange={event => onPreview({
                                          [field.key]: field.optional ? event.currentTarget.value || undefined : event.currentTarget.value,
                                      })}/>;
                }
                if (field.kind === 'number') {
                    return <NumberInput key={field.key} size="xs" label={field.label} min={field.min} max={field.max}
                                        value={values[field.key] as number}
                                        onChange={value => typeof value === 'number' && value >= field.min && value <= field.max
                                            && onPreview({[field.key]: value})}/>;
                }
                if (field.kind === 'aisles') {
                    return (single.type === 'row' || single.type === 'block') && <AislesInput key={`${single.id}-aisles-${firstRowSeatNumbers(single).join(',')}`}
                                        element={single} label={field.label} onPreview={onPreview}/>;
                }
                const options = field.kind === 'band' ? bands.map(band => ({value: band.key, label: band.name})) : field.options;
                const fallback = field.kind === 'select' ? field.fallback : undefined;
                return <Select key={field.key} size="xs" label={field.label} data={options} allowDeselect={false}
                               data-testid={`seat-map-designer-field-${field.key}`}
                               value={(values[field.key] as string | undefined) ?? fallback ?? null}
                               onChange={value => value && onApply({[field.key]: value})}/>;
            })}

            {removedSeats > 0 && (
                <Button size="xs" variant="light" onClick={onRestoreSeats}>
                    {t`Restore ${removedSeats} removed seats`}
                </Button>
            )}

            {elements.length > 1 && (
                <div className={classes.iconRow}>
                    {ALIGNMENTS.map(({edge, Icon, label}) => (
                        <Tooltip key={edge} label={label()}>
                            <ActionIcon variant="default" aria-label={label()} onClick={() => onAlign(edge)}>
                                <Icon size={16}/>
                            </ActionIcon>
                        </Tooltip>
                    ))}
                </div>
            )}

            {areas.length > 1 && (
                <Select size="xs" label={t`Move to area`} placeholder={t`Choose an area`} value={null}
                        data={areas.filter(area => area.id !== areaId).map(area => ({value: area.id, label: area.name}))}
                        onChange={value => value && onMoveToArea(value)}/>
            )}

            <div className={classes.iconRow}>
                <Button size="xs" variant="default" leftSection={<IconCopy size={14}/>} onClick={onDuplicate}>
                    {t`Duplicate`}
                </Button>
                <Button size="xs" variant="light" color="red" leftSection={<IconTrash size={14}/>} onClick={onDelete}
                        data-testid="seat-map-designer-delete-button">
                    {t`Delete`}
                </Button>
            </div>
        </section>
    );
};

type SeatAccessibility = 'none' | 'acc' | 'comp';

const accessibilityOf = (seat: GeneratedSeat): SeatAccessibility => {
    if (seat.acc) {
        return 'acc';
    }
    return seat.comp ? 'comp' : 'none';
};

interface SeatInspectorProps {
    seats: GeneratedSeat[];
    bands: SeatMapBand[];
    row: {label: string; automaticLabel: string} | null;
    onPreview: (patch: SeatOverride) => void;
    onApply: (patch: SeatOverride) => void;
    onRowLabelChange: (label: string) => void;
    onCommit: () => void;
}

export const SeatInspector = ({seats, bands, row, onPreview, onApply, onRowLabelChange, onCommit}: SeatInspectorProps) => {
    const shared = <K extends keyof GeneratedSeat>(key: K): GeneratedSeat[K] | null =>
        seats.every(seat => seat[key] === seats[0][key]) ? seats[0][key] : null;
    const accessibility = seats.every(seat => accessibilityOf(seat) === accessibilityOf(seats[0])) ? accessibilityOf(seats[0]) : null;

    return (
        <section className={classes.panelSection} onBlur={onCommit}>
            <h3 className={classes.panelHeading}>
                {seats.length === 1 ? t`Seat ${seats[0].label}` : t`${seats.length} seats selected`}
            </h3>
            <Select size="xs" label={t`Band`} allowDeselect={false} value={shared('band')}
                    data={bands.map(band => ({value: band.key, label: band.name}))}
                    onChange={value => value && onApply({band: value})}/>
            {row && (
                <TextInput size="xs" label={t`Row label`} maxLength={3} value={row.label} placeholder={row.automaticLabel}
                           description={t`Leave empty to use the automatic label`}
                           data-testid="seat-map-designer-row-label-input"
                           onChange={event => onRowLabelChange(event.currentTarget.value.replace(/[^A-Za-z0-9]/g, ''))}/>
            )}
            <Select size="xs" label={t`Accessibility`} allowDeselect={false} value={accessibility}
                    placeholder={t`Mixed`}
                    data={[
                        {value: 'none', label: t`None`},
                        {value: 'acc', label: t`Wheelchair space`},
                        {value: 'comp', label: t`Companion seat`},
                    ]}
                    onChange={value => value && onApply({acc: value === 'acc', comp: value === 'comp'})}
                    data-testid="seat-map-designer-accessibility-select"/>
            <TextInput size="xs" label={t`Note shown to buyers`} placeholder={t`Restricted view`} maxLength={255}
                       value={shared('note') ?? ''} onChange={event => onPreview({note: event.currentTarget.value})}/>
            <Button size="xs" variant="light" color="red" leftSection={<IconTrash size={14}/>}
                    onClick={() => onApply({removed: true})} data-testid="seat-map-designer-remove-seats-button">
                {t`Remove from map`}
            </Button>
        </section>
    );
};
