import {t} from "@lingui/macro";
import {Button, Card as MantineCard, Checkbox, NumberInput, Paper, Stack, Switch, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings, PaymentProvider} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Editor} from "../../../../../common/Editor";
import {InputLabelWithHelp} from "../../../../../common/InputLabelWithHelp";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";

export const PaymentAndInvoicingSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const form = useForm({
        initialValues: {
            require_billing_address: true,
            payment_providers: [] as PaymentProvider[],
            offline_payment_instructions: "",
            allow_orders_awaiting_offline_payment_to_check_in: false,
            enable_invoicing: false,
            invoice_label: "",
            invoice_prefix: "",
            invoice_start_number: 1,
            organization_name: "",
            organization_address: "",
            invoice_tax_details: "",
            invoice_payment_terms_days: null as number | null,
            invoice_notes: "",
        },
        transformValues: (values) => ({
            ...values,
            payment_providers: Array.isArray(values.payment_providers) ? values.payment_providers : [],
            offline_payment_instructions: isEmptyHtml(values.offline_payment_instructions) ? null : values.offline_payment_instructions,
            invoice_notes: isEmptyHtml(values.invoice_notes) ? null : values.invoice_notes,
            invoice_tax_details: isEmptyHtml(values.invoice_tax_details) ? null : values.invoice_tax_details,
        }),
    });

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                payment_providers: eventSettingsQuery.data.payment_providers || [],
                offline_payment_instructions: eventSettingsQuery.data.offline_payment_instructions || "",
                allow_orders_awaiting_offline_payment_to_check_in: eventSettingsQuery.data.allow_orders_awaiting_offline_payment_to_check_in || false,
                enable_invoicing: eventSettingsQuery.data.enable_invoicing || false,
                invoice_label: eventSettingsQuery.data.invoice_label || "",
                invoice_prefix: eventSettingsQuery.data.invoice_prefix || "",
                invoice_payment_terms_days: eventSettingsQuery.data.invoice_payment_terms_days || null,
                invoice_notes: eventSettingsQuery.data.invoice_notes || "",
                invoice_start_number: eventSettingsQuery.data.invoice_start_number || 1,
                require_billing_address: eventSettingsQuery.data.require_billing_address ?? true,
                organization_name: eventSettingsQuery.data.organization_name || "",
                organization_address: eventSettingsQuery.data.organization_address || "",
                invoice_tax_details: eventSettingsQuery.data.invoice_tax_details || "",
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Payment & Invoicing Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            },
        });
    };

    const paymentOptions = [
        {
            value: "STRIPE",
            label: t`Stripe`,
            description: t`Accept credit card payments with Stripe`
        },
        {
            value: "OFFLINE",
            label: t`Offline Payments`,
            description: t`Accept bank transfers, checks, or other offline payment methods`
        },
    ];

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Payment & Invoicing Settings`}
                description={t`Manage payment and invoicing settings for this event.`}
            />
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                    <Stack gap="xl">
                        <Paper withBorder p="md" radius="md">
                            <Text size="lg" fw={500} mb="md">{t`Payment Methods`}</Text>
                            {paymentOptions.map((option) => (
                                <Checkbox
                                    key={option.value}
                                    label={option.label}
                                    description={option.description}
                                    checked={form.values.payment_providers?.includes(option.value as PaymentProvider)}
                                    onChange={(event) => {
                                        const checked = event.currentTarget.checked;
                                        const currentValues = form.values.payment_providers || [];
                                        form.setFieldValue(
                                            'payment_providers',
                                            checked
                                                ? [...currentValues, option.value as PaymentProvider]
                                                : currentValues.filter(v => v !== option.value)
                                        );
                                    }}
                                    mb="sm"
                                />
                            ))}
                            {form.errors["payment_providers"] && (
                                <Text c="red">{form.errors["payment_providers"]}</Text>
                            )}

                            {form.values.payment_providers?.includes("OFFLINE") && (
                                <Card style={{boxShadow: 'none', marginTop: '20px'}}>
                                    <h4 style={{
                                        marginTop: '5px',
                                        marginBottom: '10px'
                                    }}>{t`Offline Payments Settings`}</h4>
                                    <MantineCard shadow="sm" padding="lg" radius="md" withBorder mb="md">
                                        <h4 style={{
                                            margin: 0,
                                            fontWeight: 'normal'
                                        }}>{t`Offline Payments Information`}</h4>
                                        <Text size="sm"
                                              mt="xs">{t`When offline payments are enabled, users will be able to complete their orders and receive their tickets. Their tickets will clearly indicate the order is not paid, and the check-in tool will notify the check-in staff if an order requires payment.`}</Text>
                                        <Text size="sm"
                                              mt="xs">{t`You will have to mark an order as paid manually. This can be done on the manage order page.`}</Text>
                                        <Text size="sm"
                                              mt="xs">{t`Offline orders are not reflected in event statistics until the order is marked as paid.`}</Text>
                                    </MantineCard>
                                    <Editor
                                        editorType={'simple'}
                                        value={form.values.offline_payment_instructions}
                                        error={form.errors.offline_payment_instructions as string}
                                        label={<InputLabelWithHelp label={t`Offline Payment Instructions`}
                                                                   helpText={t`This information will be shown on the payment page, order summary page, and order confirmation email.`}/>}
                                        description={t`Add instructions for offline payments (e.g., bank transfer details, where to send checks, payment deadlines)`}
                                        onChange={(value) => form.setFieldValue('offline_payment_instructions', value)}
                                    />
                                    <Switch
                                        label={t`Allow attendees associated with unpaid orders to check in`}
                                        description={t`If enabled, check-in staff can either mark attendees as checked in or mark the order as paid and check in the attendees. If disabled, attendees associated with unpaid orders cannot be checked in.`}
                                        checked={form.values.allow_orders_awaiting_offline_payment_to_check_in}
                                        {...form.getInputProps('allow_orders_awaiting_offline_payment_to_check_in', {type: 'checkbox'})}
                                    />
                                </Card>
                            )}
                        </Paper>

                        <Paper withBorder p="md" radius="md">
                            <Text size="lg" fw={500} mb="md">{t`Billing Settings`}</Text>
                            <Switch
                                label={t`Require Billing Address`}
                                description={t`Make billing address mandatory during checkout`}
                                checked={form.values.require_billing_address}
                                onChange={(event) => form.setFieldValue('require_billing_address', event.currentTarget.checked)}
                            />
                        </Paper>

                        <Paper withBorder p="md" radius="md">
                            <Text size="lg" fw={500} mb="md">{t`Invoice Settings`}</Text>

                            <Stack gap="md">
                                <Switch
                                    label={t`Enable Invoicing`}
                                    description={t`When enabled, invoices will be generated for ticket orders. Invoices will sent along with the order confirmation email. Attendees can also download their invoices from the order confirmation page.`}
                                    checked={form.values.enable_invoicing}
                                    onChange={(event) => form.setFieldValue('enable_invoicing', event.currentTarget.checked)}
                                />

                                {form.values.enable_invoicing && (
                                    <>
                                        <TextInput
                                            label={t`Document Label`}
                                            description={t`Leave blank to use the default word "Invoice"`}
                                            placeholder="Invoice"
                                            {...form.getInputProps('invoice_label')}
                                        />

                                        <Stack gap="xs">
                                            <h4 style={{margin: 0}}>{t`Invoice Numbering`}</h4>
                                            <TextInput
                                                label={t`Number Prefix`}
                                                description={t`Optional prefix for invoice numbers (e.g., INV-)`}
                                                placeholder="INV-"
                                                {...form.getInputProps('invoice_prefix')}
                                            />

                                            <NumberInput
                                                label={t`First Invoice Number`}
                                                description={t`Set the starting number for invoice numbering. This cannot be changed once invoices have been generated.`}
                                                min={1}
                                                {...form.getInputProps('invoice_start_number')}
                                            />
                                        </Stack>

                                        <Stack gap="xs">
                                            <h4 style={{margin: 0}}>{t`Payment Terms`}</h4>
                                            <NumberInput
                                                label={t`Payment Due Period`}
                                                description={t`Number of days allowed for payment (leave blank to omit payment terms from invoices)`}
                                                placeholder="30"
                                                min={0}
                                                max={365}
                                                {...form.getInputProps('invoice_payment_terms_days')}
                                            />
                                        </Stack>

                                        <Stack gap="xs">
                                            <h4 style={{margin: 0}}>{t`Organization Details`}</h4>
                                            <TextInput
                                                label={t`Organization Name`}
                                                placeholder="Your Company Ltd"
                                                {...form.getInputProps('organization_name')}
                                            />

                                            <Editor
                                                value={form.values.organization_address}
                                                label={t`Organization Address`}
                                                onChange={(value) => form.setFieldValue('organization_address', value)}
                                                error={form.errors.organization_address as string}
                                                editorType={'simple'}
                                            />

                                            <Editor
                                                value={form.values.invoice_tax_details}
                                                label={t`Tax Details`}
                                                description={t`Tax information to appear at the bottom of all invoices (e.g., VAT number, tax registration)`}
                                                onChange={(value) => form.setFieldValue('invoice_tax_details', value)}
                                                error={form.errors.invoice_tax_details as string}
                                                editorType={'simple'}
                                            />

                                            <Editor
                                                value={form.values.invoice_notes}
                                                label={t`Invoice Notes`}
                                                description={t`Optional additional information to appear on all invoices (e.g., payment terms, late payment fees, return policy)`}
                                                onChange={(value) => form.setFieldValue('invoice_notes', value)}
                                                error={form.errors.invoice_notes as string}
                                                editorType={'simple'}
                                            />
                                        </Stack>
                                    </>
                                )}
                            </Stack>
                        </Paper>

                        <Button loading={updateMutation.isPending} type="submit">
                            {t`Save`}
                        </Button>
                    </Stack>
                </fieldset>
            </form>
        </Card>
    );
};
