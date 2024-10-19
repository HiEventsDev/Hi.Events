import React, {useEffect, useState} from 'react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    DragOverEvent,
    DragOverlay,
    DragStartEvent,
    KeyboardSensor,
    PointerSensor,
    UniqueIdentifier,
    useSensor,
    useSensors
} from '@dnd-kit/core';
import {arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy} from '@dnd-kit/sortable';
import {SortableProduct} from "./SortableProduct";
import {SortableCategory} from "./SortableCategory";
import classes from "./ProductsTable.module.scss";
import {showError} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {IdParam, Product, ProductCategory} from "../../../types.ts";
import {ProductsBlankSlate} from "./ProductsBlankSlate";

export interface ProductCategoryListProps {
    initialCategories: ProductCategory[];
    event: any;
    enableSorting: boolean;
    onCreateOpen: (categoryId: IdParam) => void;
    searchTerm: string;
}

interface ItemWithId {
    id: UniqueIdentifier;
}

export const ProductCategoryList: React.FC<ProductCategoryListProps> = ({
                                                                            initialCategories,
                                                                            event,
                                                                            enableSorting,
                                                                            onCreateOpen,
                                                                            searchTerm
                                                                        }) => {
    const [categories, setCategories] = useState<ProductCategory[]>(initialCategories);
    const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
    const [activeCategoryId, setActiveCategoryId] = useState<UniqueIdentifier | null>(null);
    const [filteredCategories, setFilteredCategories] = useState<ProductCategory[]>(initialCategories);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    if (!categories || categories.length === 0 || !event) {
        return <>no categories or event</>;
    }

    console.log(categories);

    const handleDragStart = (event: DragStartEvent) => {
        const {active} = event;

        if (!active) {
            showError(t`Error moving item`);
            return;
        }

        setActiveId(active.id);
        setActiveCategoryId(categories.find(cat => cat.products.some(prod => prod.id === active.id))?.id || null);
    };

    const handleDragOver = (event: DragOverEvent) => {
        const {active, over} = event;
        if (!over) return;

        const activeCategory = categories.find(cat => cat.products.some(prod => prod.id === active.id));
        const overCategory = categories.find(cat => cat.id === over.id || cat.products.some(prod => prod.id === over.id));

        if (!activeCategory || !overCategory || activeCategory === overCategory) return;

        setCategories(prevCategories => {
            const activeIndex = activeCategory?.products.findIndex(prod => prod.id === active.id);
            return prevCategories.map(cat => {
                if (cat.id === activeCategory.id) {
                    return {
                        ...cat,
                        products: cat.products.filter(prod => prod.id !== active.id)
                    };
                }
                if (cat.id === overCategory.id) {
                    const overIndex = cat.products.findIndex(prod => prod.id === over.id);
                    const newIndex = overIndex === -1 ? cat.products.length : overIndex;
                    return {
                        ...cat,
                        products: [
                            ...cat.products.slice(0, newIndex),
                            activeCategory.products[activeIndex],
                            ...cat.products.slice(newIndex)
                        ]
                    };
                }
                return cat;
            });
        });
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const {active, over} = event;

        if (!active || !over) {
            showError(t`Error moving item`);
            return;
        }

        if (active.id !== over.id) {
            setCategories((prevCategories) => {
                const oldIndex = prevCategories.findIndex((cat) => cat.id === active.id);
                const newIndex = prevCategories.findIndex((cat) => cat.id === over.id);

                if (oldIndex !== -1 && newIndex !== -1) {
                    // Category was moved
                    return arrayMove(prevCategories, oldIndex, newIndex);
                } else {
                    // Product was moved
                    const newCategories = [...prevCategories];
                    const sourceCategory = newCategories.find(cat => cat.products.some((prod: ItemWithId) => prod.id === active.id));
                    const destCategory = newCategories.find(cat => cat.id === over.id || cat.products.some((prod: ItemWithId) => prod.id === over.id));

                    if (sourceCategory && destCategory) {
                        const [movedProduct] = sourceCategory.products.splice(sourceCategory.products.findIndex((prod: ItemWithId) => prod.id === active.id), 1);
                        const overIndex = destCategory.products.findIndex((prod: ItemWithId) => prod.id === over.id);

                        if (overIndex !== -1) {
                            destCategory.products.splice(overIndex, 0, movedProduct);
                        } else {
                            destCategory.products.push(movedProduct);
                        }
                    }

                    return newCategories;
                }
            });
        }

        setActiveId(null);
        setActiveCategoryId(null);
    };

    const findItemById = (id: IdParam): Product | ProductCategory | undefined => {
        for (const category of categories) {
            if (category.id === id) return category;
            const product = category?.products?.find(p => p.id === id);
            if (product) return product;
        }
    };

    useEffect(() => {
        if (searchTerm) {
            const filtered = initialCategories
                .map(category => ({
                    ...category,
                    products: category.products.filter(product => product.title.toLowerCase().includes(searchTerm.toLowerCase()))
                }))
                .filter(category => category.products.length > 0);

            setFilteredCategories(filtered);
        } else {
            setFilteredCategories(initialCategories);
        }
    }, [searchTerm, initialCategories]);

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={handleDragStart}
            onDragOver={handleDragOver}
            onDragEnd={handleDragEnd}
        >
            {filteredCategories.length > 0 ? (
                <SortableContext items={filteredCategories.map(cat => cat.id)} strategy={verticalListSortingStrategy}>
                    <div className={classes.categories}>
                        {filteredCategories.map((category) => (
                            <SortableCategory
                                key={category.id}
                                category={category}
                                enableSorting={enableSorting}
                                isOver={category.id === activeCategoryId}
                                isLastCategory={categories.length === 1}
                            >
                                {category.products.length === 0 && (
                                    <ProductsBlankSlate productCategories={categories}
                                                        searchTerm={searchTerm}
                                                        openCreateModal={() => onCreateOpen(category.id)}/>
                                )}
                                {category.products.length > 0 && (
                                    <SortableContext items={category.products.map((prod: ItemWithId) => prod.id)}
                                                     strategy={verticalListSortingStrategy}>
                                        <div className={classes.cards}>
                                            {category.products.map((product: Product) => (
                                                <SortableProduct
                                                    key={product.id}
                                                    product={product}
                                                    enableSorting={enableSorting}
                                                    currencyCode={event.currency}
                                                    isOver={product.id === activeId}
                                                    isDragging={false}
                                                />
                                            ))}
                                        </div>
                                    </SortableContext>
                                )}
                            </SortableCategory>
                        ))}
                    </div>
                </SortableContext>
            ) : (
                <ProductsBlankSlate
                    productCategories={categories}
                    searchTerm={searchTerm}
                    openCreateModal={onCreateOpen}
                />
            )}
            <DragOverlay>
                {activeId ? (
                    (() => {
                        const item = findItemById(activeId);
                        if (item && 'products' in item) {
                            // It's a category
                            return (
                                <div className={`${classes.sortableCategory} ${classes.isDragging}`}>
                                    <div className={classes.categoryHeader}>
                                        <h2 className={classes.categoryTitle}>{item.name}</h2>
                                    </div>
                                    <div className={classes.categoryContent}>
                                        <div className={classes.cards}>
                                            {item.products.slice(0, 2).map((product: Product) => (
                                                <SortableProduct
                                                    key={product.id}
                                                    product={product}
                                                    enableSorting={false}
                                                    currencyCode={event.currency}
                                                    isDragging={false}
                                                    isOver={true}
                                                />
                                            ))}
                                            {item.products.length > 2 && (
                                                <div className={classes.moreProducts}>
                                                    +{item.products.length - 2} more products
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        } else if (item) {
                            // It's a product
                            return (
                                <SortableProduct
                                    product={item as Product}
                                    isOver={true}
                                    enableSorting={false}
                                    currencyCode={event.currency}
                                    isDragging={true}
                                />
                            );
                        }
                        return null;
                    })()
                ) : null}
            </DragOverlay>
        </DndContext>
    );
};
