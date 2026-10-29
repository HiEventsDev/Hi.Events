import {useState} from "react";
import {Button, Group, NumberInput, SegmentedControl, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {CartDiscount} from "../../../hooks/useBoxOfficeCart.ts";
import {formatCurrency, getCurrencySymbol} from "../../../../../utilites/currency.ts";
import {Sheet} from "./Sheet.tsx";

interface OverrideSheetProps {
    opened: boolean;
    onClose: () => void;
    title: string;
    listPrice: number;
    currentPrice?: number;
    currency: string;
    onApply: (price: number | undefined) => void;
}

export const PriceOverrideSheet = ({opened, onClose, title, listPrice, currentPrice, currency, onApply}: OverrideSheetProps) => {
    const [value, setValue] = useState<number | string>(currentPrice ?? listPrice);

    return (
        <Sheet opened={opened} onClose={onClose} title={t`Change price`}>
            <Text size="sm" c="dimmed" mb="xs">{title}</Text>
            <NumberInput
                label={t`Price per item`}
                description={t`Before taxes and fees`}
                value={value}
                onChange={setValue}
                min={0}
                decimalScale={2}
                leftSection={getCurrencySymbol(currency)}
                size="md"
                inputMode="decimal"
                autoFocus
            />
            <Group mt="md" grow>
                <Button variant="light" onClick={() => setValue(0)}>{t`Free`}</Button>
                <Button variant="light" onClick={() => onApply(undefined)}>{t`Reset to ${formatCurrency(listPrice, currency)}`}</Button>
            </Group>
            <Button mt="md" fullWidth size="md" onClick={() => onApply(Number(value) || 0)} data-testid="box-office-override-apply-button">
                {t`Apply`}
            </Button>
        </Sheet>
    );
};

interface DiscountSheetProps {
    opened: boolean;
    onClose: () => void;
    totalWithDiscount: (discount: CartDiscount) => number;
    currency: string;
    current: CartDiscount | null;
    onApply: (discount: CartDiscount | null) => void;
}

export const DiscountSheet = ({opened, onClose, totalWithDiscount, currency, current, onApply}: DiscountSheetProps) => {
    const [type, setType] = useState<'FIXED' | 'PERCENTAGE'>(current?.type ?? 'PERCENTAGE');
    const [value, setValue] = useState<number | string>(current?.value ?? '');
    const numeric = Number(value) || 0;

    return (
        <Sheet opened={opened} onClose={onClose} title={t`Discount`}>
            <SegmentedControl
                fullWidth
                value={type}
                onChange={(v) => setType(v as 'FIXED' | 'PERCENTAGE')}
                data={[
                    {label: t`Percent`, value: 'PERCENTAGE'},
                    {label: t`Amount`, value: 'FIXED'},
                ]}
            />
            <NumberInput
                mt="md"
                label={type === 'PERCENTAGE' ? t`Percent off` : t`Amount off`}
                value={value}
                onChange={setValue}
                min={0}
                max={type === 'PERCENTAGE' ? 100 : undefined}
                decimalScale={2}
                leftSection={type === 'PERCENTAGE' ? '%' : getCurrencySymbol(currency)}
                size="md"
                inputMode="decimal"
                autoFocus
            />
            <Text mt="sm" size="sm" c="dimmed">
                {t`New total`}: <b>{formatCurrency(totalWithDiscount({type, value: numeric}), currency)}</b>
            </Text>
            <Group mt="md" grow>
                {current && <Button variant="light" color="red" onClick={() => onApply(null)}>{t`Remove`}</Button>}
                <Button size="md" disabled={numeric <= 0} onClick={() => onApply({type, value: numeric})}
                        data-testid="box-office-discount-apply-button">{t`Apply`}</Button>
            </Group>
        </Sheet>
    );
};
