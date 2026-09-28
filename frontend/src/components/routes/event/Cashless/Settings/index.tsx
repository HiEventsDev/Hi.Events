import {t} from "@lingui/macro";
import {
    Badge,
    Box,
    Button,
    ComboboxItem,
    Group,
    MultiSelect,
    NavLink as MantineNavLink,
    NumberInput,
    Stack,
    Switch,
    TextInput,
} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useDisclosure, useMediaQuery} from "@mantine/hooks";
import {
    IconAdjustments,
    IconAlertTriangle,
    IconCoin,
    IconLock,
    IconLockCheck,
    IconMail,
    IconReceiptRefund,
} from "@tabler/icons-react";
import {useEffect, useMemo, useState} from "react";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {Card} from "../../../../common/Card";
import {HeadingWithDescription} from "../../../../common/Card/CardHeading";
import {DangerZone, DangerZoneSection} from "../../../../common/DangerZone";
import {CashlessCloseModal} from "../../../../modals/CashlessCloseModal";
import {TemplateSettings} from "../../Settings/Sections/EmailSettings/TemplateSettings.tsx";
import {useGetCashlessSettings} from "../../../../../queries/useGetCashlessSettings.ts";
import {useGetCashlessSummary} from "../../../../../queries/useGetCashlessSummary.ts";
import {useUpdateCashlessSettings} from "../../../../../mutations/useUpdateCashlessSettings.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {getCurrencySymbol} from "../../../../../utilites/currency.ts";
import {prettyDate} from "../../../../../utilites/dates.ts";
import {useGetTaxesAndFees} from "../../../../../queries/useGetTaxesAndFees.ts";
import {taxAndFeeLabel} from "../../../../forms/ProductForm/ledgerSummaries.ts";
import {TaxAndFee, TaxAndFeeType} from "../../../../../types.ts";

interface CashlessSettingsFormValues {
    cashless_enabled: boolean;
    cashless_min_topup_amount: number | string;
    cashless_allow_remaining_balance_refund: boolean;
    cashless_refund_deadline_at: string;
    cashless_online_topup_enabled: boolean;
    cashless_topup_tax_and_fee_ids: string[];
}

const CashlessSettings = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: settings} = useGetCashlessSettings(eventId);
    const {data: summary} = useGetCashlessSummary(eventId);
    const updateMutation = useUpdateCashlessSettings();
    const errorHandler = useFormErrorResponseHandler();
    const {data: taxesAndFees} = useGetTaxesAndFees();
    const [closeModalOpen, {open: openCloseModal, close: closeCloseModal}] = useDisclosure(false);

    const taxAndFeeOptions = (type: TaxAndFeeType): ComboboxItem[] => taxesAndFees?.data
        ?.filter((item: TaxAndFee) => item.type === type)
        .map((item: TaxAndFee) => ({
            label: taxAndFeeLabel(item, event?.currency),
            value: String(item.id),
        })) || [];

    const form = useForm<CashlessSettingsFormValues>({
        initialValues: {
            cashless_enabled: false,
            cashless_min_topup_amount: 5,
            cashless_allow_remaining_balance_refund: false,
            cashless_refund_deadline_at: '',
            cashless_online_topup_enabled: true,
            cashless_topup_tax_and_fee_ids: [],
        },
    });

    useEffect(() => {
        if (!settings) {
            return;
        }

        form.setValues({
            cashless_enabled: settings.cashless_enabled,
            cashless_min_topup_amount: settings.cashless_min_topup_amount,
            cashless_allow_remaining_balance_refund: settings.cashless_allow_remaining_balance_refund,
            cashless_refund_deadline_at: settings.cashless_refund_deadline_at?.slice(0, 16) ?? '',
            cashless_online_topup_enabled: settings.cashless_online_topup_enabled,
            cashless_topup_tax_and_fee_ids: settings.cashless_topup_tax_and_fee_ids.map(String),
        });
    }, [settings]);

    const handleSubmit = form.onSubmit((values) => {
        updateMutation.mutate({
            eventId,
            settings: {
                cashless_enabled: values.cashless_enabled,
                cashless_min_topup_amount: Number(values.cashless_min_topup_amount),
                cashless_allow_remaining_balance_refund: values.cashless_allow_remaining_balance_refund,
                cashless_refund_deadline_at: values.cashless_refund_deadline_at || null,
                cashless_online_topup_enabled: values.cashless_online_topup_enabled,
                cashless_topup_tax_and_fee_ids: values.cashless_topup_tax_and_fee_ids.map(Number),
            },
        }, {
            onSuccess: () => showSuccess(t`Cashless settings saved`),
            onError: (error) => errorHandler(form, error),
        });
    });

    const showDangerZone = !!settings && (settings.cashless_enabled || !!settings.cashless_closed_at);

    const sections = useMemo(() => [
        {id: 'cashless-general', label: t`General`, icon: IconAdjustments},
        {id: 'cashless-topups', label: t`Top-ups`, icon: IconCoin},
        {id: 'cashless-refunds', label: t`Refunds`, icon: IconReceiptRefund},
        {id: 'cashless-email', label: t`Email Templates`, icon: IconMail},
        ...(showDangerZone
            ? [{id: 'cashless-danger-zone', label: t`Danger Zone`, icon: IconAlertTriangle, color: 'red'}]
            : []),
    ], [showDangerZone]);

    const isLargeScreen = useMediaQuery('(min-width: 1200px)', true);
    const [activeSection, setActiveSection] = useState(() => {
        if (typeof window === 'undefined') return 'cashless-general';
        return window.location.hash.replace('#', '') || 'cashless-general';
    });

    const handleClick = (sectionId: string) => {
        setActiveSection(sectionId);
        window.history.replaceState(null, '', `#${sectionId}`);
        document.getElementById(sectionId)?.scrollIntoView({behavior: 'smooth'});
    };

    const saveButton = (testId: string) => (
        <Button
            type="submit"
            loading={updateMutation.isPending}
            data-testid={testId}
        >
            {t`Save`}
        </Button>
    );

    const sideMenu = (
        <Card style={{padding: '15px', marginBottom: 0}}>
            <Stack gap="xs">
                {sections.map((section) => (
                    <MantineNavLink
                        style={{borderRadius: '5px'}}
                        key={section.id}
                        active={activeSection === section.id}
                        label={section.label}
                        color={'color' in section ? section.color : undefined}
                        leftSection={<section.icon size={16} stroke={1.5}/>}
                        onClick={() => handleClick(section.id)}
                    />
                ))}
            </Stack>
        </Card>
    );

    const content = (
        <>
            <form onSubmit={handleSubmit}>
                <div id="cashless-general" style={{scrollMarginTop: '20px'}}>
                    <Card>
                        <HeadingWithDescription
                            heading={t`General`}
                            description={t`Switch cashless payments on or off for this event.`}
                        />
                        <Stack gap="md">
                            <Switch
                                label={t`Enable cashless payments`}
                                description={t`Adds a top-up option to every ticket and lets your sales points take payment.`}
                                {...form.getInputProps('cashless_enabled', {type: 'checkbox'})}
                                data-testid="cashless-enabled-switch"
                            />
                            {saveButton('cashless-settings-submit-button')}
                        </Stack>
                    </Card>
                </div>

                <div id="cashless-topups" style={{scrollMarginTop: '20px'}}>
                    <Card>
                        <HeadingWithDescription
                            heading={t`Top-ups`}
                            description={t`Control how attendees load money onto their ticket.`}
                        />
                        <Stack gap="md">
                            <NumberInput
                                label={t`Minimum top-up`}
                                description={t`Card fees make very small top-ups uneconomical.`}
                                prefix={getCurrencySymbol(event?.currency ?? 'USD')}
                                min={0.01}
                                decimalScale={2}
                                {...form.getInputProps('cashless_min_topup_amount')}
                            />

                            <Switch
                                label={t`Allow top-ups from the ticket page`}
                                description={t`Turn this off to take top-ups only at your sales points.`}
                                {...form.getInputProps('cashless_online_topup_enabled', {type: 'checkbox'})}
                                data-testid="cashless-online-topup-switch"
                            />

                            <MultiSelect
                                label={t`Top-up taxes and fees`}
                                description={t`Applied on top of the amount loaded. Card top-ups pay them; cash at a sales point does not.`}
                                placeholder={t`Select...`}
                                data={[{
                                    group: t`Taxes`,
                                    items: taxAndFeeOptions(TaxAndFeeType.Tax),
                                }, {
                                    group: t`Fees`,
                                    items: taxAndFeeOptions(TaxAndFeeType.Fee),
                                }]}
                                {...form.getInputProps('cashless_topup_tax_and_fee_ids')}
                                data-testid="cashless-topup-fees-select"
                            />
                            {saveButton('cashless-topups-submit-button')}
                        </Stack>
                    </Card>
                </div>

                <div id="cashless-refunds" style={{scrollMarginTop: '20px'}}>
                    <Card>
                        <HeadingWithDescription
                            heading={t`Refunds`}
                            description={t`Decide whether attendees can get back what they did not spend.`}
                        />
                        <Stack gap="md">
                            <Switch
                                label={t`Allow refunds of unspent balance`}
                                description={t`Lets you return what attendees did not spend, to their card or in person.`}
                                {...form.getInputProps('cashless_allow_remaining_balance_refund', {type: 'checkbox'})}
                            />

                            {form.values.cashless_allow_remaining_balance_refund && (
                                <TextInput
                                    type="datetime-local"
                                    label={t`Refunds close at`}
                                    description={t`Leave blank to keep refunds open indefinitely.`}
                                    {...form.getInputProps('cashless_refund_deadline_at')}
                                />
                            )}
                            {saveButton('cashless-refunds-submit-button')}
                        </Stack>
                    </Card>
                </div>
            </form>

            <div id="cashless-email" style={{scrollMarginTop: '20px'}}>
                <TemplateSettings types={['cashless_topup']}/>
            </div>

            {showDangerZone && settings && (
                <div id="cashless-danger-zone" style={{scrollMarginTop: '20px'}}>
                    <DangerZone>
                        <DangerZoneSection
                            title={t`Close cashless`}
                            description={settings.cashless_closed_at && event
                                ? t`Cashless was closed on ${prettyDate(settings.cashless_closed_at, event.timezone)}. Every remaining balance was moved into your sales.`
                                : t`Once the event is over, close cashless to move the money left in balances into your total sales. Every balance is locked and this cannot be undone.`}
                            action={settings.cashless_closed_at ? (
                                <Badge variant="light" color="gray" leftSection={<IconLockCheck size={14}/>}>
                                    {t`Closed`}
                                </Badge>
                            ) : (
                                <Button
                                    color="red"
                                    variant="outline"
                                    leftSection={<IconLock size={16}/>}
                                    disabled={!summary}
                                    onClick={openCloseModal}
                                    data-testid="cashless-close-button"
                                >
                                    {t`Close cashless`}
                                </Button>
                            )}
                        />
                    </DangerZone>
                </div>
            )}
        </>
    );

    return (
        <PageBody>
            <PageTitle
                subheading={t`Let attendees load money onto their ticket and pay with its QR code at your bars and stands.`}
            >
                {t`Cashless Settings`}
            </PageTitle>

            {isLargeScreen ? (
                <Group align="flex-start" gap="md">
                    <Box w={240} style={{position: 'sticky', top: 20}}>
                        {sideMenu}
                    </Box>
                    <Box style={{flex: 1}}>{content}</Box>
                </Group>
            ) : (
                <Stack>
                    {sideMenu}
                    {content}
                </Stack>
            )}

            {closeModalOpen && settings && summary && (
                <CashlessCloseModal
                    settings={settings}
                    summary={summary}
                    currency={event?.currency ?? 'USD'}
                    onClose={closeCloseModal}
                />
            )}
        </PageBody>
    );
};

export default CashlessSettings;
