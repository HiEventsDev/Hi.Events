import {useState} from 'react';
import {
    IconCalendarEvent,
    IconClock,
    IconCopyPlus,
    IconDotsVertical,
    IconEyeOff,
    IconLock,
    IconPackage,
    IconPencil,
    IconReceipt,
    IconSend,
    IconTicket,
    IconTrash,
} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {Badge, Button, Group, Menu, Progress, Tooltip} from "@mantine/core";
import Truncate from "../../Truncate";
import {t, Trans} from "@lingui/macro";
import {relativeDate} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {
    IdParam,
    MessageType,
    Product,
    ProductCategory,
    ProductPrice,
    ProductPriceType,
    ProductType
} from "../../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {useDeleteProduct} from "../../../../mutations/useDeleteProduct.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
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
}

export const SortableProduct = ({product, currencyCode, category, categories}: SortableProductProps) => {
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
        deleteMutation.mutate({productId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Product deleted successfully`);
            },
            onError: (error: any) => {
                if (error.response?.status === 409) {
                    showError(error.response.data.message || t`This product cannot be deleted because it is associated with an order. You can hide it instead.`);
                }
            }
        });
    }

    const getStatusInfo = (product: Product) => {
        if (product.is_sold_out) {
            return {label: t`Sold Out`, color: 'red', variant: 'filled' as const};
        }
        if (product.is_before_sale_start_date) {
            return {label: t`Scheduled`, color: 'blue', variant: 'light' as const};
        }
        if (product.is_after_sale_end_date) {
            return {label: t`Ended`, color: 'gray', variant: 'light' as const};
        }
        if (product.is_hidden) {
            return {label: t`Hidden`, color: 'gray', variant: 'outline' as const};
        }
        return product.is_available
            ? {label: t`On Sale`, color: 'green', variant: 'light' as const}
            : {label: t`Paused`, color: 'orange', variant: 'light' as const};
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

    const getSalesProgress = () => {
        const sold = Number(product.quantity_sold) || 0;
        const initial = product.initial_quantity_available;

        if (!initial || initial <= 0) {
            return null; // Unlimited
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

    return (
        <>
            <div className={classNames(
                classes.productCard,
                {[classes.soldOut]: product.is_sold_out}
            )}>
                {/* Sort controls */}
                <div className={classes.sortControls}>
                    <SortArrows
                        upArrowEnabled={canMoveUp}
                        downArrowEnabled={canMoveDown}
                        onSortUp={() => handleSort(product.id, 'up')}
                        onSortDown={() => handleSort(product.id, 'down')}
                        flexDirection={'column'}
                    />
                </div>

                {/* Main content */}
                <div className={classes.productContent}>
                    {/* Header row with badges */}
                    <div className={classes.productHeader}>
                        <div className={classes.badgeRow}>
                            <div className={classes.typeBadges}>
                                {isTicket ? (
                                    <Badge
                                        leftSection={<IconTicket size={12} />}
                                        variant="light"
                                        color="violet"
                                        size="sm"
                                    >
                                        {t`Ticket`}
                                    </Badge>
                                ) : (
                                    <Badge
                                        leftSection={<IconPackage size={12} />}
                                        variant="light"
                                        color="cyan"
                                        size="sm"
                                    >
                                        {t`Product`}
                                    </Badge>
                                )}
                                {product.type === ProductPriceType.Donation && (
                                    <Badge
                                        variant="outline"
                                        color="pink"
                                        size="sm"
                                    >
                                        {t`Donation`}
                                    </Badge>
                                )}
                                {(product.is_hidden_without_promo_code || product.is_hidden) && (
                                    <Tooltip
                                        label={product.is_hidden
                                            ? t`Hidden from public view`
                                            : t`Only visible with promo code`}
                                        withArrow
                                    >
                                        <Badge
                                            variant="light"
                                            color="gray"
                                            size="sm"
                                            leftSection={product.is_hidden_without_promo_code ? <IconLock size={12} /> : <IconEyeOff size={12} />}
                                        >
                                            {product.is_hidden_without_promo_code ? t`Promo Only` : t`Hidden`}
                                        </Badge>
                                    </Tooltip>
                                )}
                            </div>
                            <Tooltip label={getStatusTooltip(product)} withArrow>
                                <Badge
                                    color={statusInfo.color}
                                    variant={statusInfo.variant}
                                    className={classes.statusBadge}
                                >
                                    {statusInfo.label}
                                </Badge>
                            </Tooltip>
                        </div>
                        <h3 className={classes.productTitle}>
                            <Truncate text={product.title} length={80}/>
                        </h3>
                    </div>

                    {/* Details grid */}
                    <div className={classes.detailsGrid}>
                        {/* Price */}
                        <div className={classes.detailItem}>
                            <span className={classes.detailLabel}>{t`Price`}</span>
                            <div className={classes.priceValue}>
                                <span className={classNames(
                                    classes.priceAmount,
                                    {[classes.freePrice]: priceInfo.isFree}
                                )}>
                                    {priceInfo.display}
                                </span>
                                {hasTaxesOrFees() && (
                                    <Tooltip label={getTaxFeeTooltip()} withArrow>
                                        <div className={classes.taxIndicator}>
                                            <IconReceipt size={14} />
                                            <span>{t`+Tax/Fees`}</span>
                                        </div>
                                    </Tooltip>
                                )}
                            </div>
                        </div>

                        {/* Sales / Quantity */}
                        <div className={classes.detailItem}>
                            <span className={classes.detailLabel}>
                                {isTicket ? t`Attendees` : t`Sold`}
                            </span>
                            <div className={classes.salesValue}>
                                {salesProgress ? (
                                    <div className={classes.salesWithProgress}>
                                        <span className={classes.salesCount}>
                                            {salesProgress.sold}
                                            <span className={classes.salesTotal}>/ {salesProgress.total}</span>
                                        </span>
                                        <Progress
                                            value={salesProgress.percentage}
                                            size="xs"
                                            color={salesProgress.percentage >= 100 ? 'red' : salesProgress.isLow ? 'orange' : 'green'}
                                            className={classes.salesProgress}
                                        />
                                        {salesProgress.isLow && salesProgress.remaining > 0 && (
                                            <span className={classes.lowStock}>
                                                {t`${salesProgress.remaining} left`}
                                            </span>
                                        )}
                                    </div>
                                ) : (
                                    <span className={classes.salesCount}>
                                        {Number(product.quantity_sold)}
                                        <span className={classes.unlimited}>{t`Unlimited`}</span>
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Sale period */}
                        <div className={classes.detailItem}>
                            <span className={classes.detailLabel}>{t`Sale Period`}</span>
                            <div className={classes.dateValue}>
                                {product.sale_start_date || product.sale_end_date ? (
                                    <div className={classes.dateRange}>
                                        {product.is_before_sale_start_date && product.sale_start_date && (
                                            <Tooltip label={t`Sale starts ${relativeDate(product.sale_start_date as string)}`} withArrow>
                                                <div className={classes.dateItem}>
                                                    <IconClock size={14} />
                                                    <span>{relativeDate(product.sale_start_date as string)}</span>
                                                </div>
                                            </Tooltip>
                                        )}
                                        {!product.is_before_sale_start_date && product.sale_end_date && (
                                            <Tooltip label={t`Sale ends ${relativeDate(product.sale_end_date as string)}`} withArrow>
                                                <div className={classes.dateItem}>
                                                    <IconCalendarEvent size={14} />
                                                    <span>{product.is_after_sale_end_date ? t`Ended` : relativeDate(product.sale_end_date as string)}</span>
                                                </div>
                                            </Tooltip>
                                        )}
                                        {!product.is_before_sale_start_date && !product.sale_end_date && (
                                            <span className={classes.noEndDate}>{t`No end date`}</span>
                                        )}
                                    </div>
                                ) : (
                                    <span className={classes.alwaysAvailable}>{t`Always available`}</span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className={classes.actionSection}>
                    <Group wrap="nowrap" gap={0}>
                        <Menu shadow="md" width={200} position="bottom-end">
                            <Menu.Target>
                                <div>
                                    <Button
                                        size="xs"
                                        variant="subtle"
                                        className={classes.actionButton}
                                    >
                                        <span className={classes.actionButtonText}>{t`Manage`}</span>
                                        <IconDotsVertical size={16} className={classes.actionButtonIcon} />
                                    </Button>
                                </div>
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
                                >
                                    <Trans>Edit {isTicket ? t`Ticket` : t`Product`}</Trans>
                                </Menu.Item>
                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, duplicateModal)}
                                    leftSection={<IconCopyPlus size={14}/>}
                                >
                                    {t`Duplicate`}
                                </Menu.Item>

                                <Menu.Divider />
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
                    </Group>
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
