import {RichTextEditor, useRichTextEditorContext} from '@mantine/tiptap';
import {IconBraces} from '@tabler/icons-react';
import {Group, Menu, ScrollArea, Stack, Text} from '@mantine/core';
import {EmailTemplateType} from '../../../../types';
import {t} from '@lingui/macro';

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
        {label: t`Order Number`, value: 'order.number', description: t`Unique order reference`, category: t`Order`},
        {label: t`Order Total`, value: 'order.total', description: t`Total amount paid`, category: t`Order`},
        {label: t`Order Date`, value: 'order.date', description: t`Date order was placed`, category: t`Order`},
        {label: t`Order First Name`, value: 'order.first_name', description: t`Customer's first name`, category: t`Order`},
        {label: t`Order Last Name`, value: 'order.last_name', description: t`Customer's last name`, category: t`Order`},
        {label: t`Order Email`, value: 'order.email', description: t`Customer's email address`, category: t`Order`},
        {label: t`Order URL`, value: 'order.url', description: t`Link to order details`, category: t`Order`},
        {label: t`Order Status`, value: 'order.is_pending', description: t`True if payment pending`, category: t`Order`},
        {label: t`Offline Payment`, value: 'is_offline_payment', description: t`True if offline payment`, category: t`Order`},

        // Event Information
        {label: t`Event Title`, value: 'event.title', description: t`Name of the event`, category: t`Event`},
        {label: t`Event Date`, value: 'event.date', description: t`Date of the event`, category: t`Event`},
        {label: t`Event Time`, value: 'event.time', description: t`Start time of the event`, category: t`Event`},
        {label: t`Event Location`, value: 'event.location', description: t`Venue or address`, category: t`Event`},
        {label: t`Event Description`, value: 'event.description', description: t`Event details`, category: t`Event`},

        // Organization
        {label: t`Organizer Name`, value: 'organizer.name', description: t`Event organizer name`, category: t`Organization`},
        {label: t`Organizer Email`, value: 'organizer.email', description: t`Organizer email address`, category: t`Organization`},

        // Settings
        {label: t`Support Email`, value: 'settings.support_email', description: t`Contact email for support`, category: t`Settings`},
        {label: t`Offline Payment Instructions`, value: 'settings.offline_payment_instructions', description: t`How to pay offline`, category: t`Settings`},
        {label: t`Post Checkout Message`, value: 'settings.post_checkout_message', description: t`Custom message after checkout`, category: t`Settings`},
    ],
    attendee_ticket: [
        // Attendee Information
        {label: t`Attendee Name`, value: 'attendee.name', description: t`Ticket holder's name`, category: t`Attendee`},
        {label: t`Attendee Email`, value: 'attendee.email', description: t`Ticket holder's email`, category: t`Attendee`},

        // Ticket Information
        {label: t`Ticket Name`, value: 'ticket.name', description: t`Type of ticket`, category: t`Ticket`},
        {label: t`Ticket Price`, value: 'ticket.price', description: t`Price of the ticket`, category: t`Ticket`},
        {label: t`Ticket URL`, value: 'ticket.url', description: t`Link to ticket`, category: t`Ticket`},

        // Order Information
        {label: t`Order Status`, value: 'order.is_pending', description: t`True if payment pending`, category: t`Order`},
        {label: t`Offline Payment`, value: 'is_offline_payment', description: t`True if offline payment`, category: t`Order`},

        // Event Information
        {label: t`Event Title`, value: 'event.title', description: t`Name of the event`, category: t`Event`},
        {label: t`Event Date`, value: 'event.date', description: t`Date of the event`, category: t`Event`},
        {label: t`Event Time`, value: 'event.time', description: t`Start time of the event`, category: t`Event`},
        {label: t`Event Location`, value: 'event.location', description: t`Venue or address`, category: t`Event`},
        {label: t`Event Description`, value: 'event.description', description: t`Event details`, category: t`Event`},

        // Organization
        {label: t`Organizer Name`, value: 'organizer.name', description: t`Event organizer name`, category: t`Organization`},
        {label: t`Organizer Email`, value: 'organizer.email', description: t`Organizer email address`, category: t`Organization`},

        // Settings
        {label: t`Support Email`, value: 'settings.support_email', description: t`Contact email for support`, category: t`Settings`},
        {label: t`Offline Payment Instructions`, value: 'settings.offline_payment_instructions', description: t`How to pay offline`, category: t`Settings`},
        {label: t`Post Checkout Message`, value: 'settings.post_checkout_message', description: t`Custom message after checkout`, category: t`Settings`},
    ],
};

export function InsertLiquidVariableControl({templateType = 'order_confirmation'}: InsertLiquidVariableControlProps) {
    const {editor} = useRichTextEditorContext();
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
        <Menu shadow="md" width={380}>
            <Menu.Target>
                <RichTextEditor.Control
                    title={t`Insert Variable`}
                    aria-label={t`Insert Variable`}
                >
                    <IconBraces size={16}/>
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
                                    p="sm"
                                >
                                    <Stack gap={4}>
                                        <Text size="sm" fw={500}>
                                            {variable.label}
                                        </Text>
                                        <Text 
                                            size="xs" 
                                            c="blue" 
                                            ff="monospace"
                                            style={{
                                                backgroundColor: 'var(--mantine-color-gray-0)',
                                                padding: '2px 6px',
                                                borderRadius: '4px',
                                                display: 'inline-block',
                                                width: 'fit-content'
                                            }}
                                        >
                                            {`{{ ${variable.value} }}`}
                                        </Text>
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
