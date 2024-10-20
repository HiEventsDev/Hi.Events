import {useSortable} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {IconDotsVertical, IconEyeOff, IconGripVertical, IconPencil, IconSend, IconTrash} from "@tabler/icons-react";
import classes from "../ProductsTable.module.scss";
import classNames from "classnames";
import {Badge, Button, Group, Menu, Popover} from "@mantine/core";
import Truncate from "../../Truncate";
import {t} from "@lingui/macro";
import {relativeDate} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {IdParam, MessageType, Product, ProductPrice, ProductPriceType, ProductType} from "../../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {useDeleteProduct} from "../../../../mutations/useDeleteProduct.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {EditProductModal} from "../../../modals/EditProductModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";


interface SortableProductProps {
    product: Product;
    enableSorting: boolean;
    currencyCode: string;
    isOver: boolean;
    isDragging: boolean;
}

export const SortableProduct = ({product, enableSorting, currencyCode, isOver, isDragging}: SortableProductProps) => {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
    } = useSortable({id: product.id});
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [productId, setProductId] = useState<IdParam>();
    const deleteMutation = useDeleteProduct();

    const handleModalClick = (productId: IdParam, modal: { open: () => void }) => {
        setProductId(productId);
        modal.open();
    }

    const handleDeleteProduct = (productId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({productId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Product deleted successfully`)
            },
            onError: (error: any) => {
                if (error.response?.status === 409) {
                    showError(error.response.data.message || t`This product cannot be deleted because it is
                     associated with an order. You can hide it instead.`);
                }
            }
        });
    }

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

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

        if (product.type !== ProductPriceType.Tiered) {
            if (productPrices[0].price <= 0) {
                return t`Free`;
            }
            return formatCurrency(productPrices[0].price, currencyCode);
        }

        if (productPrices.length === 0) {
            return formatCurrency(productPrices[0].price, currencyCode)
        }

        const prices = productPrices.map(productPrice => productPrice.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);

        if (minPrice <= 0 && maxPrice <= 0) {
            return t`Free`;
        }

        return formatCurrency(minPrice, currencyCode) + ' - ' + formatCurrency(maxPrice, currencyCode);
    }

    return (
        <>
            <div
                ref={setNodeRef}
                style={style}
                className={classNames(classes.productCard, {
                    [classes.isOver]: isOver,
                    [classes.isDragging]: isDragging,
                })}
            >
                <div
                    {...attributes}
                    {...listeners}
                    className={classNames(['drag-handle', classes.dragHandle, !enableSorting && classes.dragHandleDisabled])}
                >
                    <IconGripVertical size={25}/>
                </div>
                <div className={classes.productInfo}>
                    <div className={classes.productDetails}>
                        <div className={classes.title}>
                            <div className={classes.heading}>{t`Title`} {product.id}</div>
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
                                    <Badge className={classes.status}
                                           color={product.is_available ? 'green' : 'orange'} variant={"outline"}>
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
                            <div
                                className={classes.heading}>{product.product_type === ProductType.Ticket ? t`Attendees` : t`Quantity Sold`}</div>
                            {Number(product.quantity_sold)}
                        </div>
                    </div>
                </div>
                <div className={classes.action}>
                    <Group wrap={'nowrap'} gap={0}>
                        <Menu shadow="md" width={200}>
                            <Menu.Target>
                                <div>
                                    <div className={classes.mobileAction}>
                                        <Button size={"xs"} variant={"light"}>
                                            {t`Manage`}
                                        </Button>
                                    </div>
                                    <div className={classes.desktopAction}>
                                        <Button size={"xs"} variant={"transparent"}>
                                            <IconDotsVertical/>
                                        </Button>
                                    </div>
                                </div>
                            </Menu.Target>

                            <Menu.Dropdown>
                                <Menu.Label>{t`Actions`}</Menu.Label>
                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, messageModal)}
                                    leftSection={<IconSend
                                        size={14}/>}>{t`Message Attendees`}</Menu.Item>
                                <Menu.Item
                                    onClick={() => handleModalClick(product.id, editModal)}
                                    leftSection={<IconPencil
                                        size={14}/>}>{t`Edit Product`}</Menu.Item>

                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                <Menu.Item
                                    onClick={() => handleDeleteProduct(product.id, product.event_id)}
                                    color="red"
                                    leftSection={<IconTrash size={14}/>}
                                >
                                    {t`Delete product`}
                                </Menu.Item>
                            </Menu.Dropdown>
                        </Menu>
                    </Group>
                </div>

                {product.product_type === ProductType.Ticket && <div className={classes.halfCircle}/>}

                <div className={`${classes.halfCircle} ${classes.right}`}/>
            </div>
            {isEditModalOpen && <EditProductModal productId={productId}
                                                  onClose={editModal.close}
            />}
            {isMessageModalOpen && <SendMessageModal onClose={messageModal.close}
                                                     productId={productId}
                                                     messageType={MessageType.Product}
            />}
        </>
    );
};
