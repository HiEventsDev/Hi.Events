import {CSSProperties, MouseEvent, ReactNode, useState} from 'react';
import {
    IconArrowDown,
    IconArrowUp,
    IconClock,
    IconCopyPlus,
    IconDots,
    IconEyeOff,
    IconHeart,
    IconLock,
    IconPackage,
    IconPuzzle,
    IconPencil,
    IconSend,
    IconSparkles,
    IconTicket,
    IconTrash,
} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {ActionIcon, Menu, Progress, Tooltip} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {prettyDate, relativeDate} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {
    IdParam,
    MessageType,
    Product,
    ProductCategory,
    ProductPrice,
    ProductPriceType,
    ProductQuantityAppliesTo,
    ProductType
} from "../../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {useDeleteProduct} from "../../../../mutations/useDeleteProduct.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {EditProductModal} from "../../../modals/EditProductModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";
import {SortArrows} from "../../SortArrows";
import {useSortProducts} from "../../../../mutations/useSortProducts.ts";
import {DuplicateProductModal} from "../../../modals/DuplicateProductModal";

interface SortableProductProps {
    product: Product;
    currencyCode: string;
    category: ProductCategory;
    categories: ProductCategory[];
    isRecurringEvent?: boolean;
    isSeated?: boolean;
    eventTimezone: string;
}

const addonBadgeLabel = (count: number): string => count === 1 ? t`1 add-on` : t`${count} add-ons`;

interface MetaChipProps {
    icon: ReactNode;
    label: string;
    color: string;
    tooltip?: string;
}

const MetaChip = ({icon, label, color, tooltip}: MetaChipProps) => {
    const chip = (
        <span className={classes.metaChip} style={{'--chip-icon-color': `var(--mantine-color-${color}-6)`} as CSSProperties}>
            {icon}
            {label}
        </span>
    );

    return tooltip ? <Tooltip label={tooltip} withArrow multiline maw={280}>{chip}</Tooltip> : chip;
};

const stopRowClick = (event: MouseEvent) => event.stopPropagation();

export const SortableProduct = ({
                                    product,
                                    currencyCode,
                                    category,
                                    categories,
                                    isRecurringEvent,
                                    isSeated,
                                    eventTimezone,
                                }: SortableProductProps) => {
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [productId, setProductId] = useState<IdParam>();
    const deleteMutation = useDeleteProduct();
    const sortMutation = useSortProducts();

    if (!product?.id || !category?.id || !Array.isArray(category.products)) {
        return null;
    }

    const handleModalClick = (productId: IdParam, modal: { open: () => void }) => {
        setProductId(productId);
        modal.open();
    }

    const handleDeleteProduct = (productId: IdParam, eventId: IdParam) => {
        confirmationDialog(t`Delete this product? This cannot be undone.`, () => {
            deleteMutation.mutate({productId, eventId}, {
                onSuccess: () => {
                    showSuccess(t`Product deleted successfully`);
                },
                onError: (error: any) => {
                    if (error.response?.status === 409) {
                        showError(error.response.data.message || t`This product cannot be deleted because it is associated with an order. You can hide it instead.`);
                    } else {
                        showError(error.response?.data?.message || t`Failed to delete product. Please try again.`);
                    }
                }
            });
        });
    }

    const getStatusInfo = (product: Product) => {
        if (product.is_sold_out) {
            return {key: 'sold-out', label: t`Sold Out`, color: 'red'};
        }
        if (product.is_before_sale_start_date) {
            return {key: 'scheduled', label: t`Scheduled`, color: 'blue'};
        }
        if (product.is_after_sale_end_date) {
            return {key: 'ended', label: t`Ended`, color: 'gray'};
        }
        if (product.is_hidden) {
            return {key: 'hidden', label: t`Hidden`, color: 'gray'};
        }
        return product.is_available
            ? {key: 'on-sale', label: t`On Sale`, color: 'green'}
            : {key: 'paused', label: t`Paused`, color: 'orange'};
    }

    const getStatusTooltip = (product: Product) => {
        if (product.is_sold_out) return t`This product is sold out`;
        if (product.is_before_sale_start_date) return t`On sale ${relativeDate(product.sale_start_date as string)}`;
        if (product.is_after_sale_end_date) return t`Sale ended ${relativeDate(product.sale_end_date as string)}`;
        if (product.is_hidden) return t`Hidden from public view`;
        return product.is_available ? t`Currently available for purchase` : t`Sales are paused`;
    }

    const getPriceRange = (product: Product) => {
        const productPrices: ProductPrice[] = product.prices as ProductPrice[];
        if (!Array.isArray(productPrices) || productPrices.length === 0) {
            return {display: t`Price not set`, isFree: false};
        }

        if (product.type !== ProductPriceType.Tiered) {
            if (productPrices[0].price <= 0) {
                return {display: t`Free`, isFree: true};
            }
            return {display: formatCurrency(productPrices[0].price, currencyCode), isFree: false};
        }

        const prices = productPrices.map(productPrice => productPrice.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);

        if (minPrice <= 0 && maxPrice <= 0) {
            return {display: t`Free`, isFree: true};
        }

        if (minPrice === maxPrice) {
            return {display: formatCurrency(minPrice, currencyCode), isFree: false};
        }

        return {
            display: `${formatCurrency(minPrice, currencyCode)} – ${formatCurrency(maxPrice, currencyCode)}`,
            isFree: false
        };
    }

    const hasTaxesOrFees = () => {
        return (product.taxes_and_fees && product.taxes_and_fees.length > 0) ||
            (product.tax_and_fee_ids && product.tax_and_fee_ids.length > 0);
    }

    const getTaxFeeTooltip = () => {
        if (!product.taxes_and_fees || product.taxes_and_fees.length === 0) {
            return t`Taxes & fees applied`;
        }
        return product.taxes_and_fees.map(tf => tf.name).join(', ');
    }

    const quantityScope = (): 'per-date' | 'total' | 'mixed' | null => {
        const prices = product.prices ?? [];
        if (!isRecurringEvent || prices.length === 0) {
            return null;
        }
        if (prices.every(price => price.quantity_applies_to === ProductQuantityAppliesTo.Occurrence)) {
            return 'per-date';
        }
        if (prices.every(price => price.quantity_applies_to === ProductQuantityAppliesTo.Event)) {
            return 'total';
        }
        return 'mixed';
    };

    const quantityScopeLabel = (): string | null => {
        switch (quantityScope()) {
            case 'per-date':
                return t`per date`;
            case 'total':
                return t`total`;
            case 'mixed':
                return t`mixed`;
            default:
                return null;
        }
    };

    const perDateAllocation = product.initial_quantity_available;

    const getSalesProgress = () => {
        const sold = Number(product.quantity_sold) || 0;
        const initial = product.initial_quantity_available;

        if (!initial || initial <= 0) {
            return null;
        }

        const percentage = Math.min((sold / initial) * 100, 100);
        const remaining = initial - sold;

        return {
            sold,
            total: initial,
            remaining,
            percentage,
            isLow: remaining > 0 && remaining <= 10,
        };
    }

    const handleSort = (productId: IdParam, direction: 'up' | 'down') => {
        if (!category?.products?.length || !product.event_id) return;

        const categoryIndex = categories.findIndex(cat => cat.id === category.id);
        const currentIndex = category.products.findIndex(p => p.id === productId);

        if (categoryIndex === -1 || currentIndex === -1) return;

        let updatedCategories = [...categories];

        if ((direction === 'up' && currentIndex === 0) ||
            (direction === 'down' && currentIndex === category.products.length - 1)) {

            const targetCategoryIndex = direction === 'up' ? categoryIndex - 1 : categoryIndex + 1;

            if (targetCategoryIndex < 0 || targetCategoryIndex >= categories.length) return;

            const sourceProducts = [...category.products];
            const [movedProduct] = sourceProducts.splice(currentIndex, 1);

            const targetCategory = categories[targetCategoryIndex];
            const targetProducts = [...(targetCategory.products || [])];

            const targetPosition = direction === 'up' ? targetProducts.length : 0;
            targetProducts.splice(targetPosition, 0, movedProduct);

            updatedCategories = categories.map((cat, index) => {
                if (index === categoryIndex) {
                    return {...cat, products: sourceProducts};
                }
                if (index === targetCategoryIndex) {
                    return {...cat, products: targetProducts};
                }
                return cat;
            });
        } else {
            const newIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
            if (newIndex < 0 || newIndex >= category.products.length) return;

            const updatedProducts = [...category.products];
            [updatedProducts[currentIndex], updatedProducts[newIndex]] =
                [updatedProducts[newIndex], updatedProducts[currentIndex]];

            updatedCategories = categories.map(cat =>
                cat.id === category.id ? {...cat, products: updatedProducts} : cat
            );
        }

        const sortedCategories = updatedCategories.map(cat => ({
            product_category_id: cat.id,
            sorted_products: (cat.products || []).map((prod, index) => ({
                id: prod.id,
                order: index + 1
            }))
        }));

        sortMutation.mutate({
            sortedCategories,
            eventId: product.event_id,
        }, {
            onSuccess: () => showSuccess(t`Products sorted successfully`),
            onError: () => showError(t`Failed to sort products`)
        });
    };

    const currentCategoryIndex = categories.findIndex(cat => cat.id === category.id);
    const currentProducts = category.products || [];
    const currentIndex = currentProducts.findIndex(p => p.id === product.id);

    const canMoveUp = currentIndex > 0 || currentCategoryIndex > 0;
    const canMoveDown = currentIndex < currentProducts.length - 1 ||
        currentCategoryIndex < categories.length - 1;

    const isTicket = product.product_type === ProductType.Ticket;
    const statusInfo = getStatusInfo(product);
    const priceInfo = getPriceRange(product);
    const salesProgress = getSalesProgress();
    const isMixedCategory = currentProducts.some(p => p.product_type !== product.product_type);

    const handleRowClick = () => {
        if (window.getSelection()?.toString()) {
            return;
        }
        handleModalClick(product.id, editModal);
    };

    const renderSalePeriodLine = (label: string, date: string) => (
        <div className={classes.periodText}>
            <span>{label}</span>
            <span className={classes.periodDate}>{prettyDate(date, eventTimezone)}</span>
        </div>
    );

    const renderSalePeriod = () => {
        const startDate = product.sale_start_date as string | undefined;
        const endDate = product.sale_end_date as string | undefined;

        if (!startDate && !endDate) {
            return <span className={classes.periodMuted}>{t`Always available`}</span>;
        }
        if (product.is_before_sale_start_date && startDate) {
            return renderSalePeriodLine(t`Sale starts ${relativeDate(startDate)}`, startDate);
        }
        if (endDate) {
            return renderSalePeriodLine(
                product.is_after_sale_end_date
                    ? t`Sale ended ${relativeDate(endDate)}`
                    : t`Sale ends ${relativeDate(endDate)}`,
                endDate,
            );
        }
        return <span className={classes.periodMuted}>{t`No end date`}</span>;
    };

    const renderSales = () => {
        const sold = Number(product.quantity_sold) || 0;

        if (isSeated && !product.initial_quantity_available) {
            return (
                <span className={classes.salesCount}>
                    {sold}
                    <span className={classes.salesMeta}>{t`Reserved seating`}</span>
                </span>
            );
        }

        if (quantityScope() === 'per-date' && perDateAllocation) {
            return (
                <span className={classes.salesCount}>
                    {sold}
                    <span className={classes.salesMeta}>{t`up to ${perDateAllocation} per date`}</span>
                </span>
            );
        }

        if (salesProgress) {
            return (
                <div className={classes.salesWithProgress}>
                    <span className={classes.salesCount}>
                        {salesProgress.sold}
                        <span className={classes.salesTotal}>/ {salesProgress.total}</span>
                        {quantityScopeLabel() && (
                            <span className={classes.salesTotal}> {quantityScopeLabel()}</span>
                        )}
                    </span>
                    <Progress
                        value={salesProgress.percentage}
                        size={4}
                        radius="xl"
                        color={salesProgress.percentage >= 100 ? 'red' : salesProgress.isLow ? 'orange' : 'green'}
                        className={classes.salesProgress}
                    />
                    {salesProgress.isLow && salesProgress.remaining > 0 && (
                        <span className={classes.lowStock}>
                            {t`${salesProgress.remaining} left`}
                        </span>
                    )}
                </div>
            );
        }

        return (
            <span className={classes.salesCount}>
                {sold}
                <span className={classes.salesMeta}>{t`Unlimited`}</span>
            </span>
        );
    };

    const showVisibilityChip = product.is_hidden_without_promo_code
        || (product.is_hidden && statusInfo.key !== 'hidden');

    const hasChips = product.is_highlighted
        || (product.waitlist_enabled && !isSeated)
        || product.type === ProductPriceType.Donation
        || showVisibilityChip
        || product.is_addon_only
        || !!product.addons?.length;

    return (
        <>
            <div
                className={classNames(classes.productRow, {[classes.soldOut]: product.is_sold_out})}
                onClick={handleRowClick}
            >
                <div className={classes.sortControls} onClick={stopRowClick}>
                    <SortArrows
                        upArrowEnabled={canMoveUp}
                        downArrowEnabled={canMoveDown}
                        onSortUp={() => handleSort(product.id, 'up')}
                        onSortDown={() => handleSort(product.id, 'down')}
                        flexDirection={'column'}
                    />
                </div>

                <div className={classes.productMain}>
                    <div className={classes.titleLine}>
                        {isMixedCategory && (
                            <Tooltip label={isTicket ? t`Ticket` : t`Product`} withArrow>
                                <span className={classes.typeIcon}>
                                    {isTicket ? <IconTicket size={16}/> : <IconPackage size={16}/>}
                                </span>
                            </Tooltip>
                        )}
                        <h3 className={classes.productTitle}>
                            <button type="button" className={classes.titleButton} title={product.title}>
                                {product.title}
                            </button>
                        </h3>
                    </div>

                    {hasChips && (
                        <div className={classes.metaChips}>
                            {product.is_highlighted && (
                                <MetaChip
                                    icon={<IconSparkles size={13}/>}
                                    label={t`Highlighted`}
                                    color="yellow"
                                    tooltip={product.highlight_message || t`This product is highlighted on the event page`}
                                />
                            )}
                            {product.waitlist_enabled && !isSeated && (
                                <MetaChip icon={<IconClock size={13}/>} label={t`Waitlist Enabled`} color="blue"/>
                            )}
                            {product.type === ProductPriceType.Donation && (
                                <MetaChip icon={<IconHeart size={13}/>} label={t`Donation`} color="pink"/>
                            )}
                            {showVisibilityChip && (
                                <MetaChip
                                    icon={product.is_hidden_without_promo_code ? <IconLock size={13}/> : <IconEyeOff size={13}/>}
                                    label={product.is_hidden_without_promo_code ? t`Promo Only` : t`Hidden`}
                                    color="gray"
                                    tooltip={product.is_hidden
                                        ? t`Hidden from public view`
                                        : t`Only visible with promo code`}
                                />
                            )}
                            {product.is_addon_only && (
                                <MetaChip
                                    icon={<IconPuzzle size={13}/>}
                                    label={t`Add-on only`}
                                    color="teal"
                                    tooltip={t`Only shown as an add-on to the products it's attached to`}
                                />
                            )}
                            {!!product.addons?.length && (
                                <MetaChip
                                    icon={<IconPuzzle size={13}/>}
                                    label={addonBadgeLabel(product.addons.length)}
                                    color="grape"
                                    tooltip={product.addons.map(addon => addon.title).join(', ')}
                                />
                            )}
                        </div>
                    )}
                </div>

                <div className={classes.productStats}>
                    <div className={classes.statusCell}>
                        <Tooltip label={getStatusTooltip(product)} withArrow>
                            <span
                                className={classes.status}
                                style={{'--status-color': `var(--mantine-color-${statusInfo.color}-6)`} as CSSProperties}
                            >
                                <span className={classes.statusDot}/>
                                {statusInfo.label}
                            </span>
                        </Tooltip>
                    </div>

                    <div className={classes.priceCell}>
                        <span className={classNames(classes.priceAmount, {[classes.freePrice]: priceInfo.isFree})}>
                            {priceInfo.display}
                        </span>
                        {hasTaxesOrFees() && (
                            <Tooltip
                                label={getTaxFeeTooltip()}
                                withArrow
                                events={{hover: true, focus: true, touch: true}}
                            >
                                <span className={classes.taxIndicator} onClick={stopRowClick}>{t`+Tax/Fees`}</span>
                            </Tooltip>
                        )}
                    </div>

                    <div className={classes.salesCell}>
                        <span className={classes.inlineLabel}>{isTicket ? t`Attendees` : t`Sold`}</span>
                        {renderSales()}
                    </div>

                    <div className={classes.periodCell}>
                        {renderSalePeriod()}
                    </div>
                </div>

                <div className={classes.actionCell} onClick={stopRowClick}>
                    <Menu shadow="md" width={210} position="bottom-end">
                        <Menu.Target>
                            <ActionIcon
                                variant="subtle"
                                color="gray"
                                radius="md"
                                className={classes.menuButton}
                                aria-label={t`Actions`}
                                data-testid="product-manage-button"
                            >
                                <IconDots size={18}/>
                            </ActionIcon>
                        </Menu.Target>
                        <Menu.Dropdown>
                            <Menu.Label>{t`Actions`}</Menu.Label>

                            {isTicket && (
                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, messageModal)}
                                    leftSection={<IconSend size={14}/>}
                                >
                                    {t`Message Attendees`}
                                </Menu.Item>
                            )}

                            <Menu.Item
                                onClick={() => handleModalClick(product.id, editModal)}
                                leftSection={<IconPencil size={14}/>}
                                data-testid="product-edit-menu-item"
                            >
                                <Trans>Edit {isTicket ? t`Ticket` : t`Product`}</Trans>
                            </Menu.Item>
                            <Menu.Item
                                onClick={() => handleModalClick(product.id, duplicateModal)}
                                leftSection={<IconCopyPlus size={14}/>}
                            >
                                {t`Duplicate`}
                            </Menu.Item>

                            <Menu.Item
                                className={classes.touchOnly}
                                leftSection={<IconArrowUp size={14}/>}
                                disabled={!canMoveUp}
                                onClick={() => handleSort(product.id, 'up')}
                            >
                                {t`Move up`}
                            </Menu.Item>
                            <Menu.Item
                                className={classes.touchOnly}
                                leftSection={<IconArrowDown size={14}/>}
                                disabled={!canMoveDown}
                                onClick={() => handleSort(product.id, 'down')}
                            >
                                {t`Move down`}
                            </Menu.Item>

                            <Menu.Divider/>
                            <Menu.Label>{t`Danger zone`}</Menu.Label>
                            <Menu.Item
                                onClick={() => handleDeleteProduct(product.id, product.event_id)}
                                color="red"
                                leftSection={<IconTrash size={14}/>}
                            >
                                {t`Delete`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </div>
            </div>

            {isDuplicateModalOpen &&
                <DuplicateProductModal originalProductId={productId} onClose={duplicateModal.close}/>}
            {isEditModalOpen && <EditProductModal productId={productId} onClose={editModal.close}/>}
            {isMessageModalOpen && (
                <SendMessageModal
                    onClose={messageModal.close}
                    productId={productId}
                    messageType={MessageType.TicketHolders}
                />
            )}
        </>
    );
};
