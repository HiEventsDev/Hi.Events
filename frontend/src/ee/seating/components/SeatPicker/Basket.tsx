import {ReactNode, useState} from "react";
import {ActionIcon, Alert, Button, NumberInput, Radio, Select, Stack, Text} from "@mantine/core";
import {IconAlertTriangle, IconArmchair, IconMinus, IconPlus, IconX} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {htmlToText} from "../../../../utilites/helpers.ts";
import {formatPrice, SeatChoice, TicketOption} from "./ticketOptions.ts";
import classes from "./SeatPicker.module.scss";

interface BasketLine {
    key: string;
    label: string;
    choice: SeatChoice;
    options: TicketOption[];
}

interface BasketProps {
    lines: BasketLine[];
    total: number;
    extrasCount: number;
    extrasTotal: number;
    currency: string;
    orphanLabels: string[];
    minimumWarnings: string[];
    hasUnaccompaniedCompanions: boolean;
    isSoldOut: boolean;
    hasSeatsForSale: boolean;
    lostLabels: string[];
    bestAvailableOptions: TicketOption[];
    maxSeatsPerOrder: number | null;
    isFindingSeats: boolean;
    isContinuing?: boolean;
    continueLabel?: string;
    dropdownZIndex?: number;
    emptyState?: ReactNode;
    afterLines?: ReactNode;
    onFindBestAvailable: (option: TicketOption, quantity: number) => void;
    onChangeOption: (line: BasketLine, option: TicketOption) => void;
    onRemove: (line: BasketLine) => void;
    onContinue?: () => void;
}

export type {BasketLine};

export const Basket = (props: BasketProps) => {
    const [bestAvailableQuantity, setBestAvailableQuantity] = useState(2);
    const [bestAvailablePriceId, setBestAvailablePriceId] = useState<string | null>(null);
    const maxQuantity = props.maxSeatsPerOrder ?? 20;
    const selectedOption = props.bestAvailableOptions.find(option => String(option.price_id) === bestAvailablePriceId)
        ?? props.bestAvailableOptions[0];
    const setQuantity = (value: number) => setBestAvailableQuantity(Math.min(maxQuantity, Math.max(1, value)));

    return (
        <div className={props.onContinue ? classes.basket : classes.inlineBasket}>
            {props.bestAvailableOptions.length > 0 && (
                <div className={classes.bestAvailable}>
                    <Text fw={700}>{t`Not sure where to sit?`}</Text>
                    <Text size="sm">{t`Tell us how many seats you need and we will find the best ones next to each other.`}</Text>
                    {props.bestAvailableOptions.length > 1 && (
                        <Select label={t`Ticket type`} allowDeselect={false}
                                comboboxProps={{zIndex: props.dropdownZIndex}}
                                data={props.bestAvailableOptions.map(option => ({
                                    value: String(option.price_id),
                                    label: option.label,
                                }))}
                                value={String(selectedOption.price_id)} onChange={setBestAvailablePriceId}/>
                    )}
                    <div className={classes.bestAvailableRow}>
                        <div className={classes.stepper} role="group" aria-label={t`Number of seats`}>
                            <ActionIcon variant="default" size="lg" aria-label={t`Fewer seats`}
                                        disabled={bestAvailableQuantity <= 1}
                                        onClick={() => setQuantity(bestAvailableQuantity - 1)}>
                                <IconMinus size={18}/>
                            </ActionIcon>
                            <NumberInput hideControls w={56} min={1} max={maxQuantity} allowDecimal={false}
                                         aria-label={t`Number of seats`} classNames={{input: classes.stepperInput}}
                                         data-testid="best-available-quantity" value={bestAvailableQuantity}
                                         onChange={value => setBestAvailableQuantity(Number(value) || 1)}/>
                            <ActionIcon variant="default" size="lg" aria-label={t`More seats`}
                                        disabled={bestAvailableQuantity >= maxQuantity}
                                        onClick={() => setQuantity(bestAvailableQuantity + 1)}>
                                <IconPlus size={18}/>
                            </ActionIcon>
                        </div>
                        <Button loading={props.isFindingSeats} data-testid="best-available-button"
                                onClick={() => props.onFindBestAvailable(selectedOption, bestAvailableQuantity)}>
                            {bestAvailableQuantity === 1 ? t`Find 1 seat` : t`Find ${bestAvailableQuantity} seats`}
                        </Button>
                    </div>
                </div>
            )}

            {props.lostLabels.length > 0 && (
                <Alert color="red" icon={<IconAlertTriangle size={18}/>} data-testid="seat-conflict-notice">
                    {t`Someone just took ${props.lostLabels.join(', ')}. Pick another seat, or let us find similar seats for you.`}
                </Alert>
            )}

            <div className={classes.lines}>
                {props.lines.length === 0 && (
                    <Stack gap="sm" py="xs">
                        <Stack align="center" gap={4} c="dimmed">
                            <IconArmchair size={24}/>
                            <Text size="sm">
                                {!props.hasSeatsForSale
                                    ? t`Seats for this event are not on sale yet`
                                    : props.isSoldOut ? t`These seats are sold out` : t`Choose a seat on the map to add it to your order`}
                            </Text>
                        </Stack>
                        {props.emptyState}
                    </Stack>
                )}

                {props.lines.map(line => (
                    <div className={classes.line} key={line.key}>
                        <div className={classes.lineHeader}>
                            <Text fw={700}>{line.label}</Text>
                            <Button variant="subtle" color="gray" size="compact-sm" leftSection={<IconX size={14}/>}
                                    aria-label={t`Remove ${line.label}`} onClick={() => props.onRemove(line)}>
                                {t`Remove`}
                            </Button>
                        </div>
                        {line.options.length > 1 ? (
                            <Radio.Group value={String(line.choice.price_id)} label={t`Ticket type`}
                                         onChange={value => {
                                             const option = line.options.find(candidate => String(candidate.price_id) === value);
                                             if (option) {
                                                 props.onChangeOption(line, option);
                                             }
                                         }}>
                                <div className={classes.typeOptions} data-testid={`seat-line-ticket-type-${line.choice.seat_uid}`}>
                                    {line.options.map(option => (
                                        <Radio.Card key={option.price_id} value={String(option.price_id)} className={classes.typeOption}>
                                            <Radio.Indicator/>
                                            <span className={classes.grow}>
                                                <span className={classes.typeLabel}>{option.label}</span>
                                                {option.description && (
                                                    <span className={classes.typeDescription}>{htmlToText(option.description)}</span>
                                                )}
                                            </span>
                                            <span className={classes.typePrice}>{formatPrice(option.price, props.currency)}</span>
                                        </Radio.Card>
                                    ))}
                                </div>
                            </Radio.Group>
                        ) : (
                            <div>
                                <Text size="sm">
                                    {line.options[0]?.label} · {formatPrice(line.options[0]?.price ?? 0, props.currency)}
                                </Text>
                                {line.options[0]?.description && (
                                    <Text size="sm" c="dimmed">{htmlToText(line.options[0].description)}</Text>
                                )}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {props.afterLines}

            {props.orphanLabels.length > 0 && (
                <Alert color="yellow" icon={<IconAlertTriangle size={18}/>}>
                    {t`Please don't leave a single empty seat: ${props.orphanLabels.join(', ')}`}
                </Alert>
            )}

            {props.minimumWarnings.map(warning => (
                <Alert key={warning} color="yellow" icon={<IconAlertTriangle size={18}/>} data-testid="seat-minimum-warning">
                    {warning}
                </Alert>
            ))}

            {props.hasUnaccompaniedCompanions && (
                <Alert color="yellow" icon={<IconAlertTriangle size={18}/>} data-testid="seat-companion-warning">
                    {t`Companion seats can only be booked together with a wheelchair space. Add a wheelchair space or remove the companion seat.`}
                </Alert>
            )}

            {(props.onContinue || props.lines.length > 0) && (
                <div className={classes.footer}>
                    <div>
                        <Text size="sm" c="dimmed">
                            {props.lines.length === 1 ? t`1 seat` : t`${props.lines.length} seats`}
                            {props.extrasCount > 0 && ` · ${props.extrasCount === 1 ? t`1 other item` : t`${props.extrasCount} other items`}`}
                        </Text>
                        <Text fw={700} size="lg" data-testid="seat-basket-total">
                            {formatCurrency(props.total + props.extrasTotal, props.currency)}
                        </Text>
                    </div>
                    {props.onContinue && (
                        <Button size="md"
                                disabled={props.lines.length === 0 || props.orphanLabels.length > 0
                                    || props.hasUnaccompaniedCompanions || props.minimumWarnings.length > 0}
                                loading={props.isContinuing} onClick={props.onContinue} data-testid="seat-picker-continue-button">
                            {props.continueLabel ?? t`Continue`}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
};
