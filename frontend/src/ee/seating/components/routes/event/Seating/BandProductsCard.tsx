import {useMemo, useState} from "react";
import {Alert, Button, Group, MultiSelect, NumberInput, Stack, Text} from "@mantine/core";
import {IconInfoCircle, IconTicket} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Link} from "react-router";
import {IdParam, Product} from "../../../../../../types.ts";
import {SeatMapBandProducts} from "../../../../api/seat-map.client.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {formatCurrency, getCurrencySymbol, isZeroDecimalCurrency, minorUnitFactor} from "../../../../../../utilites/currency.ts";
import {SeatMapBand} from "../../../lib/types.ts";
import {Card} from "../../../../../../components/common/Card";
import classes from "./Seating.module.scss";

const adjustmentKey = (bandKey: string, productId: number) => `${bandKey}:${productId}`;

interface BandProductsCardProps {
    eventId: IdParam;
    bands: SeatMapBand[];
    bandProducts: SeatMapBandProducts[];
    capacityByBand: Record<string, number>;
    seatableProducts: Product[];
    currency: string;
    isLocked: boolean;
}

export const BandProductsCard = ({eventId, bands, bandProducts, capacityByBand, seatableProducts, currency, isLocked}: BandProductsCardProps) => {
    const mutation = useUpdateEventSeatMap(eventId);
    const [links, setLinks] = useState<Record<string, string[]>>(() => Object.fromEntries(bandProducts.map(
        band => [band.band_key, band.products.map(link => String(link.product_id))])));
    const [adjustments, setAdjustments] = useState<Record<string, number>>(() => Object.fromEntries(bandProducts.flatMap(
        band => band.products.map(link => [adjustmentKey(band.band_key, link.product_id), link.price_adjustment]))));
    const [editingPrices, setEditingPrices] = useState<Set<string>>(new Set());

    const linkableProducts = useMemo(
        () => seatableProducts.map(product => ({value: String(product.id), label: product.title})),
        [seatableProducts],
    );

    const minorFactor = minorUnitFactor(currency);

    const basePriceOf = (productId: number): number =>
        Number(seatableProducts.find(product => Number(product.id) === productId)?.prices?.[0]?.price ?? 0);

    const priceCountOf = (productId: number): number =>
        seatableProducts.find(product => Number(product.id) === productId)?.prices?.length ?? 1;

    const unlinkedBands = bands.filter(band => (capacityByBand[band.key] ?? 0) > 0 && !(links[band.key]?.length));

    const save = () => mutation.mutateAsync({
        action: 'bandProducts',
        bandProducts: Object.entries(links).map(([band_key, productIds]) => ({
            band_key,
            products: productIds.map(productId => ({
                product_id: Number(productId),
                price_adjustment: adjustments[adjustmentKey(band_key, Number(productId))] ?? 0,
            })),
        })),
    })
        .then(() => showSuccess(t`Tickets linked`))
        .catch(error => showError(firstApiError(error, t`The tickets could not be linked`)));

    if (seatableProducts.length === 0) {
        return (
            <Card>
                <h3 className={classes.sectionTitle}>{t`Tickets for each band`}</h3>
                <div className={classes.emptyTickets}>
                    <IconTicket size={28} className={classes.emptyTicketsIcon}/>
                    <Text fw={600}>{t`No tickets to link yet`}</Text>
                    <Text className={classes.muted}>
                        {t`Create a ticket, then link it to a band so buyers can pick a seat. Donation tickets and other product types can't be seated.`}
                    </Text>
                    <Button mt="xs" variant="light" component={Link} to={`/manage/event/${eventId}/products`}
                            data-testid="seating-create-ticket-button">
                        {t`Create a ticket`}
                    </Button>
                </div>
            </Card>
        );
    }

    return (
        <Card>
            <h3 className={classes.sectionTitle}>{t`Tickets for each band`}</h3>
            <Text className={classes.muted} mb="sm">
                {t`Buyers choosing a seat in a band can buy any ticket linked to it. Linked tickets are sold by seat, so their own quantity is ignored.`}
                {' '}
                {t`Each ticket keeps the price set on the ticket itself — only change it here if a band should cost more or less.`}
            </Text>
            {bands.map(band => (
                <div key={band.key} className={classes.bandRow}>
                    <div className={classes.bandName}>
                        <span className={classes.swatch} style={{background: band.color}}/>
                        {band.name}
                        <span className={classes.muted}>{t`${capacityByBand[band.key] ?? 0} places`}</span>
                    </div>
                    <Stack gap={6}>
                        <MultiSelect data={linkableProducts} value={links[band.key] ?? []} placeholder={t`Choose tickets`}
                                     data-testid={`seating-band-products-${band.key}`}
                                     onChange={value => setLinks(current => ({...current, [band.key]: value}))}/>
                        {(links[band.key] ?? []).map(productId => {
                            const key = adjustmentKey(band.key, Number(productId));
                            const title = linkableProducts.find(option => option.value === productId)?.label ?? '';
                            const base = basePriceOf(Number(productId));
                            const adjustment = adjustments[key] ?? 0;
                            const bandPrice = Math.max(0, base + adjustment / minorFactor);
                            const hasManyPrices = priceCountOf(Number(productId)) > 1;

                            if (adjustment === 0 && !editingPrices.has(key)) {
                                return (
                                    <Group key={key} gap="xs" className={classes.bandPriceRow}>
                                        <Text size="sm">{title}</Text>
                                        <Text size="sm" fw={600}>{formatCurrency(base, currency)}</Text>
                                        <Button variant="subtle" size="compact-xs"
                                                data-testid={`seating-band-adjust-${band.key}-${productId}`}
                                                onClick={() => setEditingPrices(current => new Set(current).add(key))}>
                                            {t`Change price here`}
                                        </Button>
                                    </Group>
                                );
                            }

                            return (
                                <Group key={key} gap="xs" align="flex-end" className={classes.bandPriceRow}>
                                    <NumberInput
                                        size="xs"
                                        w={210}
                                        min={0}
                                        decimalScale={isZeroDecimalCurrency(currency) ? 0 : 2}
                                        fixedDecimalScale
                                        step={1}
                                        label={t`${title} in ${band.name}`}
                                        description={hasManyPrices
                                            ? t`Applies to every tier of this ticket`
                                            : t`Normally ${formatCurrency(base, currency)}`}
                                        prefix={getCurrencySymbol(currency)}
                                        value={bandPrice}
                                        data-testid={`seating-band-adjustment-${band.key}-${productId}`}
                                        onChange={value => setAdjustments(current => ({
                                            ...current,
                                            [key]: Math.round((Number(value || 0) - base) * minorFactor),
                                        }))}/>
                                    <Button variant="subtle" size="compact-xs"
                                            data-testid={`seating-band-reset-${band.key}-${productId}`}
                                            onClick={() => {
                                                setAdjustments(current => ({...current, [key]: 0}));
                                                setEditingPrices(current => {
                                                    const next = new Set(current);
                                                    next.delete(key);
                                                    return next;
                                                });
                                            }}>
                                        {t`Use ticket price`}
                                    </Button>
                                </Group>
                            );
                        })}
                    </Stack>
                </div>
            ))}
            {unlinkedBands.length > 0 && (
                <Alert mt="sm" color="yellow" icon={<IconInfoCircle size={18}/>}>
                    {t`Seats in these bands cannot be bought until a ticket is linked: ${unlinkedBands.map(band => band.name).join(', ')}`}
                </Alert>
            )}
            {!isLocked && (
                <Button mt="md" loading={mutation.isPending} data-testid="seating-save-bands-button" onClick={save}>
                    {t`Save tickets`}
                </Button>
            )}
        </Card>
    );
};
