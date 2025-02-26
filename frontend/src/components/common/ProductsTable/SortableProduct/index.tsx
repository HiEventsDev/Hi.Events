import {useState} from 'react';
import {IconCopyPlus, IconDotsVertical, IconEyeOff, IconPencil, IconSend, IconTrash} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {Badge, Button, Group, Menu, Popover} from "@mantine/core";
import Truncate from "../../Truncate";
import {t} from "@lingui/macro";
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

    const getProductStatus = (product: Product) => {
        if (product.is_sold_out) {
            return t`Sold Out`;
        }

        if (product.is_before_sale_start_date) {
            return t`On sale` + ' ' + relativeDate(product.sale_start_date as string);
        }

        if (product.is_after_sale_end_date) {
            return t`Sale ended ` + ' ' + relativeDate(product.sale_end_date as string);
        }

        if (product.is_hidden) {
            return t`Hidden from public view`;
        }

        return product.is_available ? t`On Sale` : t`Not On Sale`;
    }

    const getPriceRange = (product: Product) => {
        const productPrices: ProductPrice[] = product.prices as ProductPrice[];
        if (!Array.isArray(productPrices) || productPrices.length === 0) {
            return t`Price not set`;
        }

        if (product.type !== ProductPriceType.Tiered) {
            if (productPrices[0].price <= 0) {
                return t`Free`;
            }
            return formatCurrency(productPrices[0].price, currencyCode);
        }

        const prices = productPrices.map(productPrice => productPrice.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);

        if (minPrice <= 0 && maxPrice <= 0) {
            return t`Free`;
        }

        if (minPrice === maxPrice) {
            return formatCurrency(minPrice, currencyCode);
        }

        return `${formatCurrency(minPrice, currencyCode)} - ${formatCurrency(maxPrice, currencyCode)}`;
    }

    const handleSort = (productId: IdParam, direction: 'up' | 'down') => {
        if (!category?.products?.length || !product.event_id) return;

        // Find current category index in all categories
        const categoryIndex = categories.findIndex(cat => cat.id === category.id);
        const currentIndex = category.products.findIndex(p => p.id === productId);

        if (categoryIndex === -1 || currentIndex === -1) return;

        let updatedCategories = [...categories];

        // Handle moving to different category
        if ((direction === 'up' && currentIndex === 0) ||
            (direction === 'down' && currentIndex === category.products.length - 1)) {

            const targetCategoryIndex = direction === 'up' ? categoryIndex - 1 : categoryIndex + 1;

            // Check if target category exists
            if (targetCategoryIndex < 0 || targetCategoryIndex >= categories.length) return;

            const sourceProducts = [...category.products];
            const [movedProduct] = sourceProducts.splice(currentIndex, 1);

            const targetCategory = categories[targetCategoryIndex];
            const targetProducts = [...(targetCategory.products || [])];

            // Insert at end if moving up, start if moving down
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
            // Handle moving within same category
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

    return (
        <>
            <div className={classNames(classes.productCard)}>
                <div>
                    <SortArrows
                        upArrowEnabled={canMoveUp}
                        downArrowEnabled={canMoveDown}
                        onSortUp={() => handleSort(product.id, 'up')}
                        onSortDown={() => handleSort(product.id, 'down')}
                        flexDirection={'column'}
                    />
                </div>
                <div className={classes.productInfo}>
                    <div className={classes.productDetails}>
                        <div className={classes.title}>
                            <div className={classes.heading}>{t`Title`}</div>
                            <Truncate text={product.title} length={60}/>
                            {(product.is_hidden_without_promo_code || product.is_hidden) && (
                                <Popover>
                                    <Popover.Target>
                                        <IconEyeOff style={{cursor: 'pointer'}} size={14}/>
                                    </Popover.Target>
                                    <Popover.Dropdown>
                                        {product.is_hidden
                                            ? t`This product is hidden from public view`
                                            : t`This product is hidden unless targeted by a Promo Code`}
                                    </Popover.Dropdown>
                                </Popover>
                            )}
                        </div>
                        <div className={classes.description}>
                            <div className={classes.heading}>{t`Status`}</div>
                            <Popover>
                                <Popover.Target>
                                    <Badge className={classes.status} color={product.is_available ? 'green' : 'orange'}
                                           variant="outline">
                                        {product.is_available ? t`On Sale` : t`Not On Sale`}
                                    </Badge>
                                </Popover.Target>
                                <Popover.Dropdown>
                                    {getProductStatus(product)}
                                </Popover.Dropdown>
                            </Popover>
                        </div>
                        <div className={classes.price}>
                            <div className={classes.heading}>{t`Price`}</div>
                            <div className={classes.priceAmount}>
                                {getPriceRange(product)}
                            </div>
                        </div>
                        <div className={classes.availability}>
                            <div className={classes.heading}>
                                {product.product_type === ProductType.Ticket ? t`Attendees` : t`Quantity Sold`}
                            </div>
                            {Number(product.quantity_sold)}
                        </div>
                    </div>
                </div>
                <div className={classes.action}>
                    <Group wrap="nowrap" gap={0}>
                        <Menu shadow="md" width={200}>
                            <Menu.Target>
                                <div>
                                    <div className={classes.mobileAction}>
                                        <Button size="xs" variant="light">
                                            {t`Manage`}
                                        </Button>
                                    </div>
                                    <div className={classes.desktopAction}>
                                        <Button size="xs" variant="transparent">
                                            <IconDotsVertical/>
                                        </Button>
                                    </div>
                                </div>
                            </Menu.Target>
                            <Menu.Dropdown>
                                <Menu.Label>{t`Actions`}</Menu.Label>

                                {product.product_type === ProductType.Ticket && (
                                    <Menu.Item
                                        onClick={() => handleModalClick(product.id, messageModal)}
                                        leftSection={<IconSend size={14}/>}>
                                        {t`Message Attendees`}
                                    </Menu.Item>
                                )}

                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, editModal)}
                                    leftSection={<IconPencil size={14}/>}>
                                    {t`Edit Product`}
                                </Menu.Item>
                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, duplicateModal)}
                                    leftSection={<IconCopyPlus size={14}/>}>
                                    {t`Duplicate Product`}
                                </Menu.Item>
                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                <Menu.Item
                                    onClick={() => handleDeleteProduct(product.id, product.event_id)}
                                    color="red"
                                    leftSection={<IconTrash size={14}/>}>
                                    {t`Delete product`}
                                </Menu.Item>
                            </Menu.Dropdown>
                        </Menu>
                    </Group>
                </div>
                {product.product_type === ProductType.Ticket && (
                    <>
                        <div className={classes.halfCircle}/>
                        <div className={`${classes.halfCircle} ${classes.right}`}/>
                    </>
                )}

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
