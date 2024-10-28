import React, {useEffect, useState} from 'react';
import {SortableProduct} from "./SortableProduct";
import {SortableCategory} from "./SortableCategory";
import classes from "./ProductsTable.module.scss";
import {IdParam, Product, ProductCategory} from "../../../types.ts";
import {ProductsBlankSlate} from "./ProductsBlankSlate";

export interface ProductCategoryListProps {
    initialCategories: ProductCategory[];
    event: any;
    onCreateOpen: (categoryId: IdParam) => void;
    searchTerm: string;
}

export const ProductCategoryList: React.FC<ProductCategoryListProps> = ({
                                                                            initialCategories,
                                                                            event,
                                                                            onCreateOpen,
                                                                            searchTerm
                                                                        }) => {
    const [categories, setCategories] = useState<ProductCategory[]>(initialCategories);
    const [filteredCategories, setFilteredCategories] = useState<ProductCategory[]>(initialCategories);

    if (!categories || categories.length === 0 || !event) {
        return <>no categories or event</>;
    }

    useEffect(() => {
        if (searchTerm) {
            const filtered = initialCategories
                .map(category => ({
                    ...category,
                    products: category.products?.filter(product => product.title.toLowerCase().includes(searchTerm.toLowerCase()))
                }))
                .filter(category => category.products ? category.products.length > 0 : false);

            setFilteredCategories(filtered);
        } else {
            setFilteredCategories(initialCategories);
        }
    }, [searchTerm, initialCategories]);

    return (
        <div>
            {filteredCategories.length > 0 ? (
                <div className={classes.categories}>
                    {filteredCategories.map((category) => {
                        if (!category?.products) return <></>;

                        return (
                            <SortableCategory
                                key={category.id}
                                category={category}
                                openCreateModal={() => onCreateOpen(category.id)}
                                isLastCategory={filteredCategories.length === 1}
                            >
                                {category.products.length === 0 && (
                                    <ProductsBlankSlate
                                        productCategories={categories}
                                        searchTerm={searchTerm}
                                        openCreateModal={() => onCreateOpen(category.id)}
                                    />
                                )}
                                {category.products.length > 0 && (
                                    <div className={classes.cards}>
                                        {category.products.map((product: Product) => (
                                            <SortableProduct
                                                key={product.id}
                                                product={product}
                                                currencyCode={event.currency}
                                                categories={categories}
                                                category={category}
                                            />
                                        ))}
                                    </div>
                                )}
                            </SortableCategory>
                        );
                    })}
                </div>
            ) : (
                <ProductsBlankSlate
                    productCategories={categories}
                    searchTerm={searchTerm}
                    openCreateModal={onCreateOpen}
                />
            )}
        </div>
    );
};
