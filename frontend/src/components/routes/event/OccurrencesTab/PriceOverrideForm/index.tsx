import {t} from "@lingui/macro";
import {
    ActionIcon,
    Button,
    NumberInput,
    Switch,
    Text,
    Tooltip,
} from "@mantine/core";
import {useParams} from "react-router";
import {useCallback, useEffect, useState} from "react";
import {IconInfoCircle, IconX} from "@tabler/icons-react";
import {
    IdParam,
    OccurrenceTierAllocation,
    ProductPriceOccurrenceOverride,
    ProductQuantityAppliesTo,
    ProductType,
} from "../../../../../types.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetEventOccurrence} from "../../../../../queries/useGetEventOccurrence.ts";
import {useGetOccurrenceProductAvailability} from "../../../../../queries/useGetOccurrenceProductAvailability.ts";
import {useGetPriceOverrides} from "../../../../../queries/useGetPriceOverrides.ts";
import {useGetProductVisibility} from "../../../../../queries/useGetProductVisibility.ts";
import {useUpsertPriceOverride} from "../../../../../mutations/useUpsertPriceOverride.ts";
import {useDeletePriceOverride} from "../../../../../mutations/useDeletePriceOverride.ts";
import {useUpdateProductVisibility} from "../../../../../mutations/useUpdateProductVisibility.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {getProductsFromEvent} from "../../../../../utilites/helpers.ts";
import classes from "./PriceOverrideForm.module.scss";
import {BookingSummary} from "../BookingSummary";

interface OccurrenceProductSettingsProps {
    occurrenceId?: IdParam;
}

interface PendingOverride {
    price?: number | null;
    quantity_available?: number | null;
}

const toNullableNumber = (value: number | string): number | null => value === '' ? null : Number(value);

export const OccurrenceProductSettings = ({occurrenceId}: OccurrenceProductSettingsProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: overrides} = useGetPriceOverrides(eventId, occurrenceId);
    const {data: visibilityData} = useGetProductVisibility(eventId, occurrenceId);
    const {data: occurrence} = useGetEventOccurrence(eventId, occurrenceId);
    const {data: availability} = useGetOccurrenceProductAvailability(eventId, occurrenceId);
    const upsertMutation = useUpsertPriceOverride();
    const deleteMutation = useDeletePriceOverride();
    const visibilityMutation = useUpdateProductVisibility();
    const [pendingOverrides, setPendingOverrides] = useState<Record<string, PendingOverride>>({});
    const [enabledProductIds, setEnabledProductIds] = useState<Set<number>>(new Set());
    const [visibilityInitialized, setVisibilityInitialized] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    const products = getProductsFromEvent(event);

    useEffect(() => {
        if (!products || visibilityInitialized) return;

        if (visibilityData && visibilityData.length > 0) {
            setEnabledProductIds(new Set(visibilityData.map(v => Number(v.product_id))));
        } else if (visibilityData) {
            setEnabledProductIds(new Set(products.map(p => p.id!)));
        }
        if (visibilityData !== undefined) {
            setVisibilityInitialized(true);
        }
    }, [visibilityData, products, visibilityInitialized]);

    const getExistingOverride = useCallback((priceId: number): ProductPriceOccurrenceOverride | undefined => {
        return overrides?.find(o => o.product_price_id === priceId);
    }, [overrides]);

    const handleToggleProduct = (productId: number, enabled: boolean) => {
        if (!enabled) {
            const liveEnabledCount = products?.filter(p => enabledProductIds.has(p.id!)).length ?? 0;
            if (liveEnabledCount <= 1 && enabledProductIds.has(productId)) {
                showError(t`At least one product must stay available for this date. To make the date inaccessible, cancel it from the schedule instead.`);
                return;
            }
        }
        setEnabledProductIds(prev => {
            const next = new Set(prev);
            if (enabled) {
                next.add(productId);
            } else {
                next.delete(productId);
            }
            return next;
        });
    };

    const handlePriceChange = (priceId: number, value: number | string) => {
        setPendingOverrides(prev => ({
            ...prev,
            [priceId]: {...prev[priceId], price: toNullableNumber(value)},
        }));
    };

    const handleQuantityChange = (priceId: number, value: number | string) => {
        setPendingOverrides(prev => ({
            ...prev,
            [priceId]: {...prev[priceId], quantity_available: toNullableNumber(value)},
        }));
    };

    const handleResetOverride = (priceId: number) => {
        const existing = getExistingOverride(priceId);
        if (!existing?.id) return;

        deleteMutation.mutate({
            eventId,
            occurrenceId,
            overrideId: existing.id,
        }, {
            onSuccess: () => {
                showSuccess(t`Override removed`);
                setPendingOverrides(prev => {
                    const next = {...prev};
                    delete next[priceId];
                    return next;
                });
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Failed to remove override`);
            },
        });
    };

    const handleSave = async () => {
        if (!products) return;
        setIsSaving(true);

        const liveEnabledProductIds = Array.from(enabledProductIds)
            .filter(id => products.some(p => p.id === id));

        if (liveEnabledProductIds.length === 0) {
            showError(t`At least one product must stay available for this date. To make the date inaccessible, cancel it from the schedule instead.`);
            setIsSaving(false);
            return;
        }

        try {
            await visibilityMutation.mutateAsync({
                eventId,
                occurrenceId,
                productIds: liveEnabledProductIds,
            });

            const disabledProductIds = new Set(
                products.filter(p => !enabledProductIds.has(p.id!)).map(p => p.id!)
            );
            const overridesToDelete = (overrides || []).filter(o => {
                const product = products.find(p => p.prices?.some(pr => pr.id === Number(o.product_price_id)));
                return product && disabledProductIds.has(product.id!);
            });

            for (const override of overridesToDelete) {
                if (override.id) {
                    await deleteMutation.mutateAsync({eventId, occurrenceId, overrideId: override.id});
                }
            }

            for (const [priceId, pending] of Object.entries(pendingOverrides)) {
                const priceProduct = products.find(p => p.prices?.some(pr => pr.id === Number(priceId)));
                if (priceProduct && !enabledProductIds.has(priceProduct.id!)) continue;

                const existing = getExistingOverride(Number(priceId));
                const price = pending.price !== undefined ? pending.price : existing?.price ?? null;
                const quantityAvailable = pending.quantity_available !== undefined
                    ? pending.quantity_available
                    : existing?.quantity_available ?? null;

                try {
                    if (price === null && quantityAvailable === null) {
                        if (existing?.id) {
                            await deleteMutation.mutateAsync({eventId, occurrenceId, overrideId: existing.id});
                        }
                        continue;
                    }

                    await upsertMutation.mutateAsync({
                        eventId,
                        occurrenceId,
                        data: {product_price_id: Number(priceId), price, quantity_available: quantityAvailable},
                    });
                } catch (error: any) {
                    showError(error?.response?.data?.message || t`Failed to save price override`);
                }
            }

            showSuccess(t`Product settings saved successfully`);
            setPendingOverrides({});
        } catch (error: any) {
            showError(error?.response?.data?.message || t`Failed to save product settings`);
        } finally {
            setIsSaving(false);
        }
    };

    if (!products || products.length === 0) {
        return <Text size="sm" c="dimmed">{t`No products configured for this event.`}</Text>;
    }

    if (!visibilityInitialized) {
        return null;
    }

    const availabilityFor = (priceId: number) => availability?.find(a => Number(a.product_price_id) === priceId);

    const perDateAllocation = (priceId: number, initial?: number): number | null => {
        const pending = pendingOverrides[priceId]?.quantity_available;
        if (pending !== undefined) {
            return pending;
        }
        return getExistingOverride(priceId)?.quantity_available ?? initial ?? null;
    };

    const tierAllocations: OccurrenceTierAllocation[] = products
        .filter(product => product.product_type === ProductType.Ticket && enabledProductIds.has(product.id!))
        .flatMap(product => (product.prices ?? []).map(price => ({
            product_price_id: price.id!,
            product_title: product.title,
            price_label: price.label ?? null,
            quantity: price.quantity_applies_to === ProductQuantityAppliesTo.Occurrence
                ? perDateAllocation(price.id!, price.initial_quantity_available)
                : null,
            applies_to: price.quantity_applies_to ?? ProductQuantityAppliesTo.Event,
        })));

    const capacity = occurrence?.capacity ?? null;
    const booked = occurrence?.used_capacity ?? 0;

    return (
        <div>
            <div className={classes.infoText}>
                <IconInfoCircle size={14}/>
                <span>{t`Configure which products are available for this date, and optionally adjust prices or per-date quantities.`}</span>
            </div>

            <BookingSummary capacity={capacity} booked={booked} allocations={tierAllocations}/>

            {products.map(product => {
                const isEnabled = enabledProductIds.has(product.id!);
                const hasPrices = isEnabled && product.prices && product.prices.length > 0;

                return (
                    <div
                        key={product.id}
                        className={`${classes.productCard} ${!isEnabled ? classes.disabled : ''}`}
                        style={{marginBottom: 12}}
                    >
                        <div className={classes.productHeader}>
                            <span className={classes.productName}>{product.title}</span>
                            <Switch
                                checked={isEnabled}
                                onChange={(e) => handleToggleProduct(product.id!, e.currentTarget.checked)}
                                size="sm"
                            />
                        </div>

                        {hasPrices && (
                            <div className={classes.priceTable}>
                                <div className={classes.priceHeaderRow}>
                                    <span>{t`Price Tier`}</span>
                                    <span>{t`Base Price`}</span>
                                    <span>{t`Price this date`}</span>
                                    <span>{t`Quantity this date`}</span>
                                    <span/>
                                </div>
                                {product.prices!.map(price => {
                                    const existing = getExistingOverride(price.id!);
                                    const priceAvailability = availabilityFor(price.id!);
                                    const sold = priceAvailability?.quantity_sold ?? 0;
                                    const left = priceAvailability?.quantity_available ?? null;
                                    const pending = pendingOverrides[price.id!];
                                    const displayPrice = pending?.price !== undefined
                                        ? pending.price
                                        : existing?.price;
                                    const displayQuantity = pending?.quantity_available !== undefined
                                        ? pending.quantity_available
                                        : existing?.quantity_available;
                                    const perDateQuantity = price.quantity_applies_to === ProductQuantityAppliesTo.Occurrence;

                                    return (
                                        <div key={price.id} className={classes.priceRow}>
                                            <span className={classes.priceLabel}>
                                                {price.label || t`Default`}
                                                {priceAvailability && (
                                                    <span className={classes.priceMeta} data-testid="occurrence-price-availability">
                                                        {left === null
                                                            ? t`${sold} sold · unlimited`
                                                            : perDateQuantity ? t`${sold} sold · ${left} left` : t`${sold} sold · ${left} left across all dates`}
                                                    </span>
                                                )}
                                            </span>
                                            <span className={classes.basePrice}>
                                                {price.price?.toFixed(2)}
                                            </span>
                                            <NumberInput
                                                size="xs"
                                                placeholder={t`Base price`}
                                                value={displayPrice ?? ''}
                                                onChange={(val) => handlePriceChange(price.id!, val)}
                                                min={0}
                                                decimalScale={2}
                                                fixedDecimalScale
                                                className={classes.overrideInput}
                                            />
                                            {perDateQuantity ? (
                                                <NumberInput
                                                    size="xs"
                                                    placeholder={price.initial_quantity_available != null
                                                        ? String(price.initial_quantity_available)
                                                        : t`Unlimited`}
                                                    value={displayQuantity ?? ''}
                                                    onChange={(val) => handleQuantityChange(price.id!, val)}
                                                    min={0}
                                                    allowDecimal={false}
                                                    className={classes.quantityInput}
                                                    data-testid="occurrence-override-quantity-input"
                                                />
                                            ) : (
                                                <Tooltip label={t`This tier's quantity is shared across all dates`}>
                                                    <span className={classes.basePrice}>–</span>
                                                </Tooltip>
                                            )}
                                            <span className={classes.priceRowAction}>
                                                {existing && (
                                                    <Tooltip label={t`Remove overrides for this date`}>
                                                        <ActionIcon
                                                            size="sm"
                                                            variant="subtle"
                                                            color="gray"
                                                            loading={deleteMutation.isPending}
                                                            onClick={() => handleResetOverride(price.id!)}
                                                        >
                                                            <IconX size={14}/>
                                                        </ActionIcon>
                                                    </Tooltip>
                                                )}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                );
            })}

            <Button
                loading={isSaving}
                onClick={handleSave}
                fullWidth
                className={classes.saveButton}
            >
                {t`Save Changes`}
            </Button>
        </div>
    );
};
