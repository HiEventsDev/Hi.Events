import {Modal} from "../../common/Modal";
import {GenericModalProps, ProductCategory, ProductType} from "../../../types.ts";
import {Button} from "../../common/Button";
import {useNavigate, useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {LoadingOverlay, NumberInput, Select, Switch, TextInput} from "@mantine/core";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {CreateAttendeeRequest} from "../../../api/attendee.client.ts";
import {useCreateAttendee} from "../../../mutations/useCreateAttendee.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {useEffect} from "react";
import {InputGroup} from "../../common/InputGroup";
import {
    getClientLocale,
    getLocaleName,
    localeToFlagEmojiMap,
    localeToNameMap,
    SupportedLocales
} from "../../../locales.ts";
import {ProductSelector} from "../../common/ProductSelector";
import {getProductsFromEvent} from "../../../utilites/helpers.ts";

export const CreateAttendeeModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const mutation = useCreateAttendee();
    const navigate = useNavigate();
    const eventProducts = getProductsFromEvent(event);
    const eventHasProducts = eventProducts && eventProducts?.length > 0;

    const form = useForm<CreateAttendeeRequest>({
        initialValues: {
            product_id: undefined,
            email: '',
            first_name: '',
            last_name: '',
            amount_paid: 0.00,
            send_confirmation_email: true,
            taxes_and_fees: [],
            locale: getClientLocale() as SupportedLocales,
        },
    });

    useEffect(() => {
        if (event?.product_categories) {
            form.setFieldValue(
                'product_price_id',
                String(eventProducts?.find(product => product.id == form.values.product_id)?.prices?.[0]?.id)
            );

            const taxesAndFees = eventProducts
                ?.find(product => product.id == form.values.product_id)
                ?.taxes_and_fees;

            if (taxesAndFees?.length === 0) {
                form.setFieldValue('taxes_and_fees', []);
            }

            taxesAndFees?.forEach((tax, index) => {
                    form.setFieldValue(
                        `taxes_and_fees.${index}`,
                        {
                            tax_or_fee_id: tax.id,
                            amount: 0.00,
                            name: tax.name,
                        },
                    );
                }
            );
        }
    }, [form.values.product_id]);

    const handleSubmit = (values: CreateAttendeeRequest) => {
        mutation.mutate({
            eventId: eventId,
            attendeeData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully created attendee`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    };

    if (!event?.product_categories) {
        return (
            <LoadingOverlay visible/>
        )
    }

    if (isEventFetched && !eventHasProducts) {
        return (
            <Modal opened onClose={onClose} heading={t`Manually Add Attendee`}>
                <p>{t`You must create a ticket before you can manually add an attendee.`}</p>
                <Button
                    fullWidth
                    variant={'light'}
                    onClick={() => {
                        navigate(`/manage/event/${eventId}/products`)
                    }}
                >
                    {t`Manage tickets`}
                </Button>
            </Modal>
        )
    }

    return (
        <Modal opened onClose={onClose} heading={t`Manually Add Attendee`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <InputGroup>
                    <TextInput
                        {...form.getInputProps('first_name')}
                        label={t`First name`}
                        placeholder={t`Patrick`}
                        required
                    />

                    <TextInput
                        {...form.getInputProps('last_name')}
                        label={t`Last name`}
                        placeholder={t`Johnson`}
                        required
                    />
                </InputGroup>
                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Email address`}
                    placeholder={t`patrick@acme.com`}
                    required
                />

                <Select
                    required
                    data={Object.keys(localeToNameMap).map(locale => ({
                        value: locale,
                        label: localeToFlagEmojiMap[locale as SupportedLocales] + ' ' + getLocaleName(locale as SupportedLocales),
                    }))}
                    {...form.getInputProps('locale')}
                    label={t`Language`}
                    placeholder={t`English`}
                    description={t`The language the attendee will receive emails in.`}
                />

                <ProductSelector
                    placeholder={t`Select Ticket`}
                    label={t`Ticket`}
                    productCategories={event.product_categories as ProductCategory[]}
                    form={form}
                    productFieldName={'product_id'}
                    multiSelect={false}
                    showTierSelector={true}
                    includedProductTypes={[ProductType.Ticket]}
                />

                <NumberInput
                    required
                    mt={20}
                    fixedDecimalScale
                    {...form.getInputProps('amount_paid')}
                    label={<Trans>Amount paid ({event?.currency})</Trans>}
                    placeholder="0.00"
                    decimalScale={2}
                    step={1}
                    min={0}
                    description={t`Enter an amount excluding taxes and fees.`}
                />

                {form.values.taxes_and_fees?.map((tax, index) => {
                        return (
                            <NumberInput
                                required
                                mt={20}
                                fixedDecimalScale
                                {...form.getInputProps(`taxes_and_fees.${index}.amount`)}
                                label={tax.name + ' ' + t`paid` + ' (' + event?.currency + ')'}
                                placeholder="0.00"
                                decimalScale={2}
                                step={1}
                                min={0}
                            />
                        )
                    }
                )}

                <Switch
                    mt={20}
                    label={t`Send order confirmation and ticket email`}
                    {...form.getInputProps('send_confirmation_email', {type: 'checkbox'})}
                />
                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working` + '...' : t`Create Attendee`}
                </Button>
            </form>
        </Modal>
    );
}
