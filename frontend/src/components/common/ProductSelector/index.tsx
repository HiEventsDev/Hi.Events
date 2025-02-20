import {MultiSelect, Select} from "@mantine/core";
import {IconTicket} from "@tabler/icons-react";
import {UseFormReturnType} from "@mantine/form";
import {ProductCategory, ProductType} from "../../../types.ts";
import React from "react";
import {t} from "@lingui/macro";

interface ProductSelectorProps {
    label: string;
    placeholder: string;
    icon?: React.ReactNode;
    productCategories: ProductCategory[];
    form: UseFormReturnType<any>;
    productFieldName: string;
    tierFieldName?: string;
    includedProductTypes?: ProductType[];
    multiSelect?: boolean;
    showTierSelector?: boolean;
    noProductsMessage?: string;
}

export const ProductSelector = ({
                                    label,
                                    placeholder,
                                    icon = <IconTicket size="1rem"/>,
                                    productCategories,
                                    form,
                                    productFieldName,
                                    tierFieldName = 'product_price_id',
                                    includedProductTypes = [ProductType.Ticket, ProductType.General],
                                    multiSelect = true,
                                    showTierSelector = false,
                                    noProductsMessage = t`No products available for selection`,
                                }: ProductSelectorProps) => {
    const formattedData = productCategories?.map((category) => ({
        group: category.name,
        items:
            category.products
                ?.filter((product) => includedProductTypes.includes(product.product_type))
                ?.map((product) => ({
                    value: String(product.id),
                    label: product.title,
                })) || [],
    }));
    const eventProducts = productCategories?.flatMap(category => category.products).filter(product => product !== undefined);

    if (!eventProducts || eventProducts.length === 0) {
        return (
            <Select
                label={label}
                placeholder={noProductsMessage}
                disabled
                {...form.getInputProps(productFieldName)}
            />
        );
    }

    const TierSelector = () => {
        return (
            <>
                {eventProducts?.find(product => product.id == form.values.product_id)?.type === 'TIERED' && (
                    <Select
                        label={t`Product Tier`}
                        mt={20}
                        placeholder={t`Select Product Tier`}
                        {...form.getInputProps(tierFieldName)}
                        data={eventProducts?.find(product => product.id == form.values.product_id)?.prices?.map(price => {
                            return {
                                value: String(price.id),
                                label: String(price.label),
                            };
                        })}
                    />
                )}
            </>
        );
    }

    if (multiSelect) {
        return (
            <>
                <MultiSelect
                    label={label}
                    placeholder={placeholder}
                    multiple
                    data={formattedData}
                    leftSection={icon}
                    {...form.getInputProps(productFieldName)}
                />
                {showTierSelector && <TierSelector/>}
            </>

        );
    } else {
        return (
            <>
                <Select
                    label={label}
                    placeholder={placeholder}
                    data={formattedData}
                    leftSection={icon}
                    {...form.getInputProps(productFieldName)}
                />
                {showTierSelector && <TierSelector/>}
            </>

        );
    }
};
