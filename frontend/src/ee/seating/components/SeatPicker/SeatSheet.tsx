import {useState} from "react";
import {ActionIcon, Alert, Button, Group, Modal, NumberInput, Stack, Text} from "@mantine/core";
import {IconAccessible, IconInfoCircle, IconMinus, IconPlus, IconUsers} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {priceSummary, TicketOption} from "./ticketOptions.ts";
import classes from "./SeatPicker.module.scss";

export interface SeatSheetTarget {
    uid: string;
    label: string;
    note: string | null;
    accessible: boolean;
    companion: boolean;
    needsWheelchairSpace: boolean;
    options: TicketOption[];
    zoneRemaining?: number;
}

interface SeatSheetProps {
    target: SeatSheetTarget | null;
    currency: string;
    zIndex?: number;
    onConfirm: (uid: string, quantity: number) => void;
    onClose: () => void;
}

export const SeatSheet = ({target, ...props}: SeatSheetProps) => target
    ? <SeatSheetContent key={target.uid} target={target} {...props}/>
    : null;

const SeatSheetContent = ({target, currency, zIndex, onConfirm, onClose}: SeatSheetProps & {target: SeatSheetTarget}) => {
    const [quantity, setQuantity] = useState(1);
    const isZone = target.zoneRemaining !== undefined;
    const maxQuantity = Math.max(1, target.zoneRemaining ?? 1);
    const clamp = (value: number) => Math.min(maxQuantity, Math.max(1, value));
    const price = priceSummary(target.options.map(option => option.price), currency);

    return (
        <Modal opened onClose={onClose} title={target.label} centered zIndex={zIndex}>
            <Stack gap="md">
                {target.accessible && (
                    <Alert icon={<IconAccessible size={18}/>} color="blue">
                        {t`This space is reserved for wheelchair users and guests with access needs.`}
                    </Alert>
                )}
                {target.companion && (
                    <Alert icon={<IconUsers size={18}/>} color={target.needsWheelchairSpace ? 'red' : 'blue'}
                           data-testid="seat-sheet-companion-notice">
                        {target.needsWheelchairSpace
                            ? t`This is a companion seat for someone accompanying a wheelchair user. Choose a wheelchair space first, then add this seat.`
                            : t`This is a companion seat for someone accompanying a wheelchair user.`}
                    </Alert>
                )}
                {target.note && (
                    <Alert icon={<IconInfoCircle size={18}/>} color="yellow">{target.note}</Alert>
                )}

                {price && <Text fw={600}>{price}</Text>}

                {isZone && (
                    <div>
                        <Text fw={600} mb={4}>{t`How many?`}</Text>
                        <div className={classes.stepper}>
                            <ActionIcon variant="default" size="lg" aria-label={t`Fewer places`}
                                        disabled={quantity <= 1} onClick={() => setQuantity(clamp(quantity - 1))}>
                                <IconMinus size={18}/>
                            </ActionIcon>
                            <NumberInput hideControls w={64} min={1} max={maxQuantity} allowDecimal={false}
                                         aria-label={t`How many?`} classNames={{input: classes.stepperInput}}
                                         value={quantity} onChange={value => setQuantity(Number(value) || 1)}/>
                            <ActionIcon variant="default" size="lg" aria-label={t`More places`}
                                        disabled={quantity >= maxQuantity} onClick={() => setQuantity(clamp(quantity + 1))}>
                                <IconPlus size={18}/>
                            </ActionIcon>
                        </div>
                        <Text size="sm" c="dimmed" mt={4}>{t`You can add up to ${maxQuantity}`}</Text>
                    </div>
                )}

                {target.options.length > 1 && (
                    <Text size="sm" c="dimmed">{t`You can choose the ticket type for each place in your order.`}</Text>
                )}

                <Group justify="flex-end">
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button onClick={() => onConfirm(target.uid, isZone ? clamp(quantity) : 1)}
                            disabled={target.needsWheelchairSpace} data-testid="seat-sheet-confirm-button">
                        {isZone ? t`Add to order` : t`Choose this seat`}
                    </Button>
                </Group>
            </Stack>
        </Modal>
    );
};
