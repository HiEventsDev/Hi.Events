import { RichTextEditor, useRichTextEditorContext } from '@mantine/tiptap';
import { IconCode } from '@tabler/icons-react';
import { Menu, ScrollArea, Text, Group, Stack } from '@mantine/core';
import { EmailTemplateType } from '../../../../types';
import { t } from '@lingui/macro';

interface InsertLiquidVariableControlProps {
    templateType?: EmailTemplateType;
}

interface TemplateVariable {
    label: string;
    value: string;
    description?: string;
    category?: string;
}

const TEMPLATE_VARIABLES: Record<EmailTemplateType, TemplateVariable[]> = {
    order_confirmation: [
        // Order Information
        { label: t`Order Number`, value: 'order_number', description: t`Unique order reference`, category: t`Order` },
        { label: t`Order Total`, value: 'order_total', description: t`Total amount paid`, category: t`Order` },
        { label: t`Order First Name`, value: 'order_first_name', description: t`Customer's first name`, category: t`Order` },
        { label: t`Order Last Name`, value: 'order_last_name', description: t`Customer's last name`, category: t`Order` },
        { label: t`Order Email`, value: 'order_email', description: t`Customer's email address`, category: t`Order` },
        { label: t`Order Status`, value: 'order_is_pending', description: t`True if payment pending`, category: t`Order` },
        { label: t`Offline Payment`, value: 'is_offline_payment', description: t`True if offline payment`, category: t`Order` },
        
        // Event Information
        { label: t`Event Title`, value: 'event_title', description: t`Name of the event`, category: t`Event` },
        { label: t`Event Date`, value: 'event_date', description: t`Date of the event`, category: t`Event` },
        { label: t`Event Time`, value: 'event_time', description: t`Start time of the event`, category: t`Event` },
        { label: t`Event Location`, value: 'event_location', description: t`Venue or address`, category: t`Event` },
        { label: t`Event Description`, value: 'event_description', description: t`Event details`, category: t`Event` },
        
        // Settings
        { label: t`Offline Payment Instructions`, value: 'offline_payment_instructions', description: t`How to pay offline`, category: t`Settings` },
        { label: t`Post Checkout Message`, value: 'post_checkout_message', description: t`Custom message after checkout`, category: t`Settings` },
        
        // Organization
        { label: t`Organizer Name`, value: 'organizer_name', description: t`Event organizer name`, category: t`Organization` },
        { label: t`Support Email`, value: 'support_email', description: t`Contact email for support`, category: t`Organization` },
        
        // URLs
        { label: t`Order URL`, value: 'order_url', description: t`Link to order details`, category: t`Links` },
    ],
    attendee_ticket: [
        // Attendee Information
        { label: t`Attendee Name`, value: 'attendee_name', description: t`Ticket holder's name`, category: t`Attendee` },
        { label: t`Attendee Email`, value: 'attendee_email', description: t`Ticket holder's email`, category: t`Attendee` },
        { label: t`Ticket Name`, value: 'ticket_name', description: t`Type of ticket`, category: t`Attendee` },
        { label: t`Ticket Price`, value: 'ticket_price', description: t`Price of the ticket`, category: t`Attendee` },
        
        // Order Information
        { label: t`Order Status`, value: 'order_is_pending', description: t`True if payment pending`, category: t`Order` },
        { label: t`Offline Payment`, value: 'is_offline_payment', description: t`True if offline payment`, category: t`Order` },
        
        // Event Information
        { label: t`Event Title`, value: 'event_title', description: t`Name of the event`, category: t`Event` },
        { label: t`Event Date`, value: 'event_date', description: t`Date of the event`, category: t`Event` },
        { label: t`Event Time`, value: 'event_time', description: t`Start time of the event`, category: t`Event` },
        { label: t`Event Location`, value: 'event_location', description: t`Venue or address`, category: t`Event` },
        { label: t`Event Description`, value: 'event_description', description: t`Event details`, category: t`Event` },
        
        // Settings
        { label: t`Offline Payment Instructions`, value: 'offline_payment_instructions', description: t`How to pay offline`, category: t`Settings` },
        { label: t`Post Checkout Message`, value: 'post_checkout_message', description: t`Custom message after checkout`, category: t`Settings` },
        
        // Organization
        { label: t`Organizer Name`, value: 'organizer_name', description: t`Event organizer name`, category: t`Organization` },
        { label: t`Support Email`, value: 'support_email', description: t`Contact email for support`, category: t`Organization` },
        
        // URLs
        { label: t`Ticket URL`, value: 'ticket_url', description: t`Link to ticket`, category: t`Links` },
    ],
};

export function InsertLiquidVariableControl({ templateType = 'order_confirmation' }: InsertLiquidVariableControlProps) {
    const { editor } = useRichTextEditorContext();
    const variables = TEMPLATE_VARIABLES[templateType] || [];

    const handleInsertVariable = (variable: string) => {
        // Insert as plain text with Liquid syntax
        editor?.chain().focus().insertContent(`{{ ${variable} }}`).run();
    };

    // Group variables by category
    const groupedVariables = variables.reduce((acc, variable) => {
        const category = variable.category || t`Other`;
        if (!acc[category]) {
            acc[category] = [];
        }
        acc[category].push(variable);
        return acc;
    }, {} as Record<string, TemplateVariable[]>);

    return (
        <Menu shadow="md" width={320}>
            <Menu.Target>
                <RichTextEditor.Control
                    title={t`Insert Variable`}
                    aria-label={t`Insert Variable`}
                >
                    <IconCode size={16} />
                </RichTextEditor.Control>
            </Menu.Target>

            <Menu.Dropdown>
                <ScrollArea h={400}>
                    {Object.entries(groupedVariables).map(([category, categoryVariables]) => (
                        <div key={category}>
                            <Menu.Label>{category}</Menu.Label>
                            {categoryVariables.map((variable) => (
                                <Menu.Item
                                    key={variable.value}
                                    onClick={() => handleInsertVariable(variable.value)}
                                    p="xs"
                                >
                                    <Stack gap={2}>
                                        <Group justify="space-between" wrap="nowrap">
                                            <Text size="sm" fw={500}>
                                                {variable.label}
                                            </Text>
                                            <Text size="xs" c="dimmed" ff="monospace">
                                                {`{{ ${variable.value} }}`}
                                            </Text>
                                        </Group>
                                        {variable.description && (
                                            <Text size="xs" c="dimmed">
                                                {variable.description}
                                            </Text>
                                        )}
                                    </Stack>
                                </Menu.Item>
                            ))}
                        </div>
                    ))}
                </ScrollArea>
            </Menu.Dropdown>
        </Menu>
    );
}