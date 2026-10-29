import {t} from "@lingui/macro";
import {Button, Checkbox, Input, NumberInput, Paper, Stack, Switch, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings, PaymentProvider} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {formatCurrency, plainAmount} from "../../../../../../utilites/currency.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Editor} from "../../../../../common/Editor";
import {LiquidTokenControl} from "../../../../../common/Editor/Controls/LiquidTokenControl";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";
import {useAccess} from "../../../../../../hooks/useAccess.ts";
import {UserGeneratedContent} from "../../../../../common/UserGeneratedContent";
import {OfflinePaymentDetails} from "../../../../../common/OfflinePaymentDetails";
import classes from "./PaymentSettings.module.scss";

export const PaymentAndInvoicingSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventQuery = useGetEvent(eventId);
    const updateMutation = useUpdateEventSettings();
    const canEditOfflineInstructions = useAccess().can('MANAGE_PAYMENTS');
    const form = useForm({
        initialValues: {
            require_billing_address: true,
            payment_providers: [] as PaymentProvider[],
            offline_payment_instructions: "",
            allow_orders_awaiting_offline_payment_to_check_in: false,
            offline_payment_reporting_enabled: true,
            offline_payment_order_reference_enabled: true,
            payment_links_enabled: false,
            payment_link_expiry_hours: 72 as number | string,
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
            offline_payment_instructions: canEditOfflineInstructions
                ? (isEmptyHtml(values.offline_payment_instructions) ? null : values.offline_payment_instructions)
                : (eventSettingsQuery.data?.offline_payment_instructions ?? null),
            payment_link_expiry_hours: values.payment_link_expiry_hours === ''
                && !(values.payment_links_enabled && values.payment_providers.includes('STRIPE'))
                ? 72
                : values.payment_link_expiry_hours,
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
                offline_payment_reporting_enabled: eventSettingsQuery.data.offline_payment_reporting_enabled ?? true,
                offline_payment_order_reference_enabled: eventSettingsQuery.data.offline_payment_order_reference_enabled ?? true,
                payment_links_enabled: eventSettingsQuery.data.payment_links_enabled ?? false,
                payment_link_expiry_hours: eventSettingsQuery.data.payment_link_expiry_hours ?? 72,
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
                                <div className={classes.offline}>
                                    <Text size="sm" c="dimmed">
                                        {t`Buyers get their tickets straight away, marked as unpaid. Mark each order as paid once the money arrives. Unpaid orders aren't counted in your sales figures until then.`}
                                    </Text>

                                    <div className={classes.group}>
                                        <Text className={classes.groupTitle}>{t`How buyers pay`}</Text>
                                        {canEditOfflineInstructions ? (
                                            <Editor
                                                editorType={'simple'}
                                                required
                                                value={form.values.offline_payment_instructions}
                                                error={form.errors.offline_payment_instructions as string}
                                                label={t`Instructions`}
                                                description={t`Tell buyers how to pay, such as your bank details, where to send a cheque, or that they pay at the door. Include any deadline. Buyers see this on their order page and in their confirmation email once they've placed their order.`}
                                                onChange={(value) => form.setFieldValue('offline_payment_instructions', value)}
                                                additionalToolbarControls={
                                                    <LiquidTokenControl
                                                        templateType={'order_confirmation'}
                                                        excludeTokens={[
                                                            '{{ settings.offline_payment_instructions }}',
                                                            '{{ settings.post_checkout_message }}',
                                                        ]}
                                                    />
                                                }
                                            />
                                        ) : (
                                            <Input.Wrapper
                                                label={t`Instructions`}
                                                description={t`Only members who can manage payments can change these instructions.`}
                                                error={form.errors.offline_payment_instructions as string}
                                            >
                                                <Paper withBorder p="sm" radius="sm">
                                                    {isEmptyHtml(form.values.offline_payment_instructions)
                                                        ? <Text size="sm" c="dimmed">{t`No instructions have been added yet.`}</Text>
                                                        : <UserGeneratedContent html={form.values.offline_payment_instructions}/>}
                                                </Paper>
                                            </Input.Wrapper>
                                        )}
                                        <Switch
                                            data-testid="offline-payment-order-reference-switch"
                                            label={t`Ask buyers to use their order reference when paying`}
                                            description={t`It's shown above your instructions so you can match every payment to its order. Turn this off if you give buyers your own reference, or if none applies, such as cash at the door.`}
                                            checked={form.values.offline_payment_order_reference_enabled}
                                            {...form.getInputProps('offline_payment_order_reference_enabled', {type: 'checkbox'})}
                                        />
                                        {form.values.offline_payment_order_reference_enabled && (
                                            <div className={classes.preview} data-testid="payment-reference-preview">
                                                <Text className={classes.previewLabel}>{t`What buyers see`}</Text>
                                                <div className={classes.previewBody}>
                                                    <OfflinePaymentDetails amount={formatCurrency(25, eventQuery.data?.currency)} amountToCopy={plainAmount(25, eventQuery.data?.currency ?? 'USD')} reference="O-ABC1234"/>
                                                    {isEmptyHtml(form.values.offline_payment_instructions)
                                                        ? <Text size="sm" c="dimmed">{t`Your instructions appear here.`}</Text>
                                                        : <UserGeneratedContent html={form.values.offline_payment_instructions}/>}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className={classes.group}>
                                        <Text className={classes.groupTitle}>{t`Unpaid orders`}</Text>
                                        <Switch
                                            data-testid="offline-payment-reporting-switch"
                                            label={t`Let buyers tell you when they have paid`}
                                            description={t`Adds an "I've paid" button to unpaid orders. You and your team get an email when a buyer uses it. You still mark the order as paid yourself.`}
                                            checked={form.values.offline_payment_reporting_enabled}
                                            {...form.getInputProps('offline_payment_reporting_enabled', {type: 'checkbox'})}
                                        />
                                        <Switch
                                            label={t`Let guests from unpaid orders check in`}
                                            description={t`Check-in staff are told the order is unpaid and can mark it as paid at the door. When this is off, guests from unpaid orders can't be checked in.`}
                                            checked={form.values.allow_orders_awaiting_offline_payment_to_check_in}
                                            {...form.getInputProps('allow_orders_awaiting_offline_payment_to_check_in', {type: 'checkbox'})}
                                        />
                                    </div>

                                    <div className={classes.group}>
                                        <Text className={classes.groupTitle}>{t`Someone else paying`}</Text>
                                        <Switch
                                            data-testid="payment-links-switch"
                                            label={t`Let someone else pay for an order`}
                                            description={t`Buyers can send a payment link to whoever is paying, such as their finance team. The person paying can use any payment method you accept.`}
                                            checked={form.values.payment_links_enabled}
                                            {...form.getInputProps('payment_links_enabled', {type: 'checkbox'})}
                                        />
                                        {(form.values.payment_links_enabled && form.values.payment_providers?.includes('STRIPE')) && (
                                            <NumberInput
                                                className={classes.holdHours}
                                                styles={{wrapper: {maxWidth: 200}}}
                                                label={t`Hold places for`}
                                                description={t`How long places stay reserved while waiting for payment through a link. Holds never run past the start of the event.`}
                                                rightSection={<Text size="sm" c="dimmed">{t`hours`}</Text>}
                                                rightSectionWidth={60}
                                                min={1}
                                                max={720}
                                                allowDecimal={false}
                                                allowNegative={false}
                                                hideControls
                                                {...form.getInputProps('payment_link_expiry_hours')}
                                            />
                                        )}
                                    </div>
                                </div>
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
