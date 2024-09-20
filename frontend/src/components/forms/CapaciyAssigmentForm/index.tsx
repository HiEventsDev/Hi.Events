import {InputGroup} from "../../common/InputGroup";
import {MultiSelect, NumberInput, Switch, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CapacityAssignmentRequest, Product} from "../../../types.ts";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconCheck, IconTicket, IconX} from "@tabler/icons-react";

interface CapaciyAssigmentFormProps {
    form: UseFormReturnType<CapacityAssignmentRequest>;
    products: Product[],
}

export const CapaciyAssigmentForm = ({form, products}: CapaciyAssigmentFormProps) => {
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
            description: t`Disable this capacity track capacity without stopping product sales`,
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

            <MultiSelect
                label={t`What products should this question be apply to?`}
                multiple
                placeholder={t`Select products`}
                data={products?.map(product => {
                    return {
                        value: String(product.id),
                        label: product.title,
                    }
                })}
                leftSection={<IconTicket size="1rem"/>}
                {...form.getInputProps('product_ids')}
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
