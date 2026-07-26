import {UseFormReturnType} from "@mantine/form";
import {Button, Input, NumberInput, SegmentedControl, Select, TextInput} from "@mantine/core";
import {IconBulb, IconPercentage, IconRefresh, IconTicket} from "@tabler/icons-react";
import {PromoCode, PromoCodeDiscountAppliesTo, PromoCodeDiscountType} from "../../../types.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useParams} from "react-router";
import {useEffect, useState} from "react";
import {LoadingMask} from "../../common/LoadingMask";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";
import {getCurrencySymbol} from "../../../utilites/currency.ts";
import {ProductSelector} from "../../common/ProductSelector";
import {ShowForDesktop, ShowForMobile} from "../../common/Responsive/ShowHideComponents.tsx";
import {Callout} from "../../common/Callout";
import {AdvancedOptions} from "../../common/AdvancedOptions";

interface PromoCodeFormProps {
    form: UseFormReturnType<PromoCode>,
}

const hasAdvancedValuesSet = (form: UseFormReturnType<PromoCode>): boolean => {
    return !!(
        (form.values.applicable_product_ids?.length ?? 0) > 0
        || form.values.expiry_date
        || form.values.max_allowed_usages
    );
};

export const PromoCodeForm = ({form}: PromoCodeFormProps) => {
    const {eventId} = useParams();
    const {data: event, data: {product_categories: productCategories} = {}} = useGetEvent(eventId);

    const [showAdvanced, setShowAdvanced] = useState(() => hasAdvancedValuesSet(form));

    useEffect(() => {
        if (hasAdvancedValuesSet(form)) {
            setShowAdvanced(true);
        }
    }, [form.values.applicable_product_ids, form.values.expiry_date, form.values.max_allowed_usages]);

    const currencySymbol = getCurrencySymbol(event?.currency as string);

    const DiscountIcon = () => {
        if (form.values.discount_type === 'PERCENTAGE') {
            return <IconPercentage/>;
        }
        return currencySymbol;
    };

    if (!event || !productCategories) {
        return <LoadingMask/>
    }

    const generateRandomCode = () => {
        const randomCode = Math.random().toString(36).substring(2, 10).toUpperCase();
        form.setFieldValue('code', randomCode);
    };

    return (
        <>
            <TextInput
                {...form.getInputProps('code')}
                label={t`Code`}
                placeholder="20OFF"
                required
                rightSection={(
                    <Button
                        variant="subtle"
                        size="xs"
                        color="gray"
                        onClick={generateRandomCode}
                        style={{fontWeight: 400}}
                        title={t`Generate code`}
                        leftSection={<IconRefresh size={16}/>}
                    >
                        <ShowForMobile>
                            {t`Generate`}
                        </ShowForMobile>
                        <ShowForDesktop>
                            {t`Generate code`}
                        </ShowForDesktop>
                    </Button>
                )}
                rightSectionWidth={'auto'}
            />

            <InputGroup>
                <Select
                    {...form.getInputProps('discount_type')}
                    label={t`Discount Type`}
                    data={[
                        {
                            value: 'NONE',
                            label: t`No Discount`,
                        },
                        {
                            value: 'PERCENTAGE',
                            label: t`Percentage`,
                        },
                        {
                            value: 'FIXED',
                            label: t`Fixed amount`,
                        },
                    ]}/>
                <NumberInput
                    disabled={form.values.discount_type === PromoCodeDiscountType.None}
                    decimalScale={2} min={0}
                    rightSection={<DiscountIcon/>}
                    {...form.getInputProps('discount')}
                    label={(form.values.discount_type === 'PERCENTAGE' ? t`Discount %` : t`Discount in ${event.currency}`)}
                    placeholder="0.00"/>
            </InputGroup>

            {form.values.discount_type === PromoCodeDiscountType.Fixed && (
                <Input.Wrapper
                    label={t`How is the discount applied?`}
                    description={form.values.discount_applies_to === PromoCodeDiscountAppliesTo.Order
                        ? t`The discount is deducted once from the order total.`
                        : t`The discount is deducted from every eligible product. E.g., ${currencySymbol}10 off × 3 tickets = ${currencySymbol}30 off.`}
                >
                    <SegmentedControl
                        fullWidth
                        mt={4}
                        value={form.values.discount_applies_to ?? PromoCodeDiscountAppliesTo.EachProduct}
                        onChange={(value) => form.setFieldValue('discount_applies_to', value as PromoCodeDiscountAppliesTo)}
                        data={[
                            {
                                value: PromoCodeDiscountAppliesTo.Order,
                                label: t`Entire order`,
                            },
                            {
                                value: PromoCodeDiscountAppliesTo.EachProduct,
                                label: t`Each product`,
                            },
                        ]}
                        data-testid="promo-code-discount-applies-to"
                    />
                </Input.Wrapper>
            )}

            {form.values.discount_type === PromoCodeDiscountType.None && (
                <Callout
                    icon={<IconBulb size={18} stroke={2}/>}
                    variant="info"
                    title={t`Quick Tip`}
                >
                    {t`A promo code with no discount can be used to reveal hidden products.`}
                </Callout>
            )}

            <AdvancedOptions
                opened={showAdvanced}
                onToggle={() => setShowAdvanced(v => !v)}
                dataTestId="promo-code-advanced-toggle"
            >
                <ProductSelector
                    label={t`What products does this code apply to? (Applies to all by default)`}
                    placeholder={t`Select products`}
                    icon={<IconTicket size="1rem"/>}
                    productCategories={productCategories}
                    form={form}
                    productFieldName="applicable_product_ids"
                />

                <InputGroup>
                    <TextInput type={'datetime-local'}
                               {...form.getInputProps('expiry_date')}
                               label={t`Expiry Date`}
                    />
                    <NumberInput min={1}
                                 placeholder={t`Unlimited`}
                                 {...form.getInputProps('max_allowed_usages')}
                                 label={t`How many times can this code be used?`}/>
                </InputGroup>
            </AdvancedOptions>
        </>
    );
};
