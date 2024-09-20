import {useEffect} from 'react';
import classes from './ProductsTable.module.scss';
import {NoResultsSplash} from "../NoResultsSplash";
import {t} from "@lingui/macro";
import {
    closestCenter,
    DndContext,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {SortableContext, verticalListSortingStrategy,} from '@dnd-kit/sortable';
import {Product, Event} from "../../../types";
import {useSortProducts} from "../../../mutations/useSortProducts.ts";
import {useParams} from "react-router-dom";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {SortableProduct} from "./SortableProduct";
import {useDragItemsHandler} from "../../../hooks/useDragItemsHandler.ts";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";

interface ProductCardProps {
    products: Product[];
    event: Event;
    enableSorting: boolean;
    openCreateModal: () => void;
}

export const ProductsTable = ({products, event, openCreateModal, enableSorting = false}: ProductCardProps) => {
    const {eventId} = useParams();
    const sortProductsMutation = useSortProducts();
    const {items, setItems, handleDragEnd} = useDragItemsHandler({
        initialItemIds: products.map((product) => Number(product.id)),
        onSortEnd: (newArray) => {
            sortProductsMutation.mutate({
                sortedProducts: newArray.map((id, index) => {
                    return {id, order: index + 1};
                }),
                eventId: eventId,
            }, {
                onSuccess: () => {
                    showSuccess(t`Products sorted successfully`);
                },
                onError: () => {
                    showError(t`An error occurred while sorting the products. Please try again or refresh the page`);
                }
            })
        },
    });

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor)
    );

    useEffect(() => {
        setItems(products.map((product) => Number(product.id)));
    }, [products]);

    if (products.length === 0) {
        return <NoResultsSplash
            imageHref={'/blank-slate/products.svg'}
            heading={t`No products to show`}
            subHeading={(
                <>
                    <p>
                        {t`You'll need at least one product to get started. Free, paid or let the user decide what to pay.`}
                    </p>
                    <Button
                        size={'xs'}
                        leftSection={<IconPlus/>}
                        color={'green'}
                        onClick={() => openCreateModal()}>{t`Create a Product`}
                    </Button>
                </>
            )}
        />;
    }

    const handleDragStart = (event: any) => {
        if (!enableSorting) {
            showError(t`Please remove filters and set sorting to "Homepage order" to enable sorting`);
            event.cancel();
        }
    }

    return (
        <DndContext onDragStart={handleDragStart}
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
        >
            <SortableContext items={items as UniqueIdentifier[]} strategy={verticalListSortingStrategy}>
                <div className={classes.cards}>
                    {items.map((productId) => {
                        const product = products.find((t) => t.id === productId);

                        if (!product) {
                            return null;
                        }

                        return (
                            <SortableProduct
                                key={productId}
                                product={product}
                                enableSorting={enableSorting}
                                currencyCode={event.currency}
                            />
                        );
                    })}
                </div>
            </SortableContext>
        </DndContext>
    );
};
