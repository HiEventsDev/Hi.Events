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
import {IdParam, ProductPriceOccurrenceOverride} from "../../../../../types.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetPriceOverrides} from "../../../../../queries/useGetPriceOverrides.ts";
import {useGetProductVisibility} from "../../../../../queries/useGetProductVisibility.ts";
import {useUpsertPriceOverride} from "../../../../../mutations/useUpsertPriceOverride.ts";
import {useDeletePriceOverride} from "../../../../../mutations/useDeletePriceOverride.ts";
import {useUpdateProductVisibility} from "../../../../../mutations/useUpdateProductVisibility.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {getProductsFromEvent} from "../../../../../utilites/helpers.ts";
import classes from "./PriceOverrideForm.module.scss";

interface OccurrenceProductSettingsProps {
    occurrenceId?: IdParam;
}

export const OccurrenceProductSettings = ({occurrenceId}: OccurrenceProductSettingsProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: overrides} = useGetPriceOverrides(eventId, occurrenceId);
    const {data: visibilityData} = useGetProductVisibility(eventId, occurrenceId);
    const upsertMutation = useUpsertPriceOverride();
    const deleteMutation = useDeletePriceOverride();
    const visibilityMutation = useUpdateProductVisibility();
    const [pendingOverrides, setPendingOverrides] = useState<Record<string, number | undefined>>({});
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
            [priceId]: value === '' ? undefined : Number(value),
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

        try {
            await visibilityMutation.mutateAsync({
                eventId,
                occurrenceId,
                productIds: Array.from(enabledProductIds),
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

            const priceEntries = Object.entries(pendingOverrides).filter(([, val]) => val !== undefined);

            for (const [priceId, price] of priceEntries) {
                const priceProduct = products.find(p => p.prices?.some(pr => pr.id === Number(priceId)));
                if (priceProduct && !enabledProductIds.has(priceProduct.id!)) continue;

                try {
                    await upsertMutation.mutateAsync({
                        eventId,
                        occurrenceId,
                        data: {product_price_id: Number(priceId), price: price!},
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

    return (
        <div>
            <div className={classes.infoText}>
                <IconInfoCircle size={14}/>
                <span>{t`Configure which products are available for this occurrence and optionally adjust pricing.`}</span>
            </div>

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
                            <table className={classes.priceTable}>
                                <thead>
                                    <tr className={classes.priceHeaderRow}>
                                        <th>{t`Price Tier`}</th>
                                        <th>{t`Base Price`}</th>
                                        <th>{t`Override`}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {product.prices!.map(price => {
                                        const existing = getExistingOverride(price.id!);
                                        const pendingValue = pendingOverrides[price.id!];
                                        const displayPrice = pendingValue !== undefined
                                            ? pendingValue
                                            : existing?.price;

                                        return (
                                            <tr key={price.id} className={classes.priceRow}>
                                                <td>
                                                    <span className={classes.priceLabel}>
                                                        {price.label || t`Default`}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span className={classes.basePrice}>
                                                        {price.price?.toFixed(2)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <NumberInput
                                                        size="xs"
                                                        placeholder={t`Override price`}
                                                        value={displayPrice ?? ''}
                                                        onChange={(val) => handlePriceChange(price.id!, val)}
                                                        min={0}
                                                        decimalScale={2}
                                                        fixedDecimalScale
                                                        className={classes.overrideInput}
                                                    />
                                                </td>
                                                <td>
                                                    {existing && (
                                                        <Tooltip label={t`Reset to base price`}>
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
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
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
