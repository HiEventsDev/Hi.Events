import {InputGroup} from "../../common/InputGroup";
import {MultiSelect, NumberInput, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CapacityAssignmentRequest, Ticket} from "../../../types.ts";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconCheck, IconTicket, IconX} from "@tabler/icons-react";

interface CapacityAssigmentFormProps {
    form: UseFormReturnType<CapacityAssignmentRequest>;
    tickets: Ticket[],
}

export const CapacityAssigmentForm = ({form, tickets}: CapacityAssigmentFormProps) => {
    const statusOptions: ItemProps[] = [
        {
            icon: <IconCheck/>,
            label: t`Active`,
            value: 'ACTIVE',
            description: t`Enable this capacity to stop ticket sales when the limit is reached`,
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

            <MultiSelect
                label={t`What tickets should this question be apply to?`}
                multiple
                placeholder={t`Select tickets`}
                data={tickets?.map(ticket => {
                    return {
                        value: String(ticket.id),
                        label: ticket.title,
                    }
                })}
                leftSection={<IconTicket size="1rem"/>}
                {...form.getInputProps('ticket_ids')}
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
