import {InputGroup} from "../../common/InputGroup";
import {NumberInput, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CapacityAssignmentRequest, ProductCategory} from "../../../types.ts";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconCheck, IconX} from "@tabler/icons-react";
import {ProductSelector} from "../../common/ProductSelector";

interface CapacityAssigmentFormProps {
    form: UseFormReturnType<CapacityAssignmentRequest>;
    productsCategories: ProductCategory[],
}

export const CapacityAssigmentForm = ({form, productsCategories}: CapacityAssigmentFormProps) => {
    const statusOptions: ItemProps[] = [
        {
            icon: <IconCheck/>,
            label: t`Active`,
            value: 'ACTIVE',
            description: t`Enable this capacity to stop product sales when the limit is reached`,
        },
        {
            icon: <IconX/>,
            label: t`Inactive`,
            value: 'INACTIVE',
            description: t`Disabling this capacity will track sales but not stop them when the limit is reached`,
        },
    ];

    return (
        <>
            <InputGroup>
                <TextInput
                    {...form.getInputProps('name')}
                    required
                    label={t`Name`}
                    placeholder={t`Day one capacity`}
                />
                <NumberInput
                    {...form.getInputProps('capacity')}
                    label={t`Capacity`}
                    placeholder={t`Unlimited`}
                />
            </InputGroup>

            <ProductSelector
                label={t`What products should this capacity apply to?`}
                placeholder={t`Select products`}
                productCategories={productsCategories}
                form={form}
                productFieldName={'product_ids'}
            />

            <CustomSelect
                label={t`Status`}
                required
                form={form}
                name={'status'}
                optionList={statusOptions}
            />
        </>
    );
}
