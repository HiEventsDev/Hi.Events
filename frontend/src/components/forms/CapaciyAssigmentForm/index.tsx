import {InputGroup} from "../../common/InputGroup";
import {MultiSelect, NumberInput, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CapacityAssignmentRequest, Ticket} from "../../../types.ts";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconCalendarEvent, IconCheck, IconTicket, IconX} from "@tabler/icons-react";

interface CapaciyAssigmentFormProps {
    form: UseFormReturnType<CapacityAssignmentRequest>;
    tickets: Ticket[],
}

export const CapaciyAssigmentForm = ({form, tickets}: CapaciyAssigmentFormProps) => {
    const ticketOptions: ItemProps[] = [
        {
            icon: <IconCalendarEvent/>,
            label: t`Applies to entire event`,
            value: 'EVENT',
            description: t`Capaciy applies to the entire event`,
        },
        {
            icon: <IconTicket/>,
            label: t`Applies to specific tickets`,
            value: 'TICKETS',
            description: t`Capacity applies to specific tickets`,
        },
    ];

    const statusOptions: ItemProps[] = [
        {
            icon: <IconCheck/>,
            label: t`Active`,
            value: 'ACTIVE',
            description: t`Enabled this capacity and enforce it during ticket sales`,
        },
        {
            icon: <IconX/>,
            label: t`Inactive`,
            value: 'INACTIVE',
            description: t`Disable this capacity and do not enforce it during ticket sales`,
        },
    ];

    return (
        <>
            <InputGroup>
                <TextInput
                    {...form.getInputProps('name')}
                    required
                    label={t`Name`}
                    placeholder={t`Day One Capacity`}
                />
                <NumberInput
                    {...form.getInputProps('capacity')}
                    label={t`Capacity`}
                    placeholder={t`Unlimited`}
                />
            </InputGroup>

            <CustomSelect
                label={t`What does this capacity apply to?`}
                required
                form={form}
                name={'applies_to'}
                optionList={ticketOptions}
            />

            {form.values.applies_to === 'TICKETS' && (
                <MultiSelect
                    mt={20}
                    label={t`What tickets should this question be apply to?`}
                    multiple
                    data={tickets?.map(ticket => {
                        return {
                            value: String(ticket.id),
                            label: ticket.title,
                        }
                    })}
                    leftSection={<IconTicket size="1rem"/>}
                    {...form.getInputProps('ticket_ids')}
                />
            )}

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
