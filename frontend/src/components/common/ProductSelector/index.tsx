import {MultiSelect} from "@mantine/core";
import {IconTicket} from "@tabler/icons-react";
import {UseFormReturnType} from "@mantine/form";
import {ProductCategory, ProductType} from "../../../types.ts";
import React from "react";

interface ProductSelectorProps {
    label: string;
    placeholder: string;
    icon?: React.ReactNode;
    data: ProductCategory[];
    form: UseFormReturnType<any>;
    fieldName: string;
    includedProductTypes?: ProductType[];
}

export const ProductSelector = ({
                                    label,
                                    placeholder,
                                    icon = <IconTicket size="1rem"/>,
                                    data,
                                    form,
                                    fieldName,
                                    includedProductTypes = [ProductType.Ticket, ProductType.General],
                                }: ProductSelectorProps) => {
    return (
        <MultiSelect
            label={label}
            placeholder={placeholder}
            multiple
            data={data?.map((category) => ({
                group: category.name,
                items: category.products
                    ?.filter((product) => includedProductTypes.includes(product.product_type))
                    ?.map((product) => ({
                        value: String(product.id),
                        label: product.title,
                    })) || [],
            }))}
            leftSection={icon}
            {...form.getInputProps(fieldName)}
        />
    );
};
