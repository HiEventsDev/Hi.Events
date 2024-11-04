import {UseFormReturnType} from "@mantine/form";
import {Alert, NumberInput, Select, TextInput} from "@mantine/core";
import {IconAlertCircle, IconPercentage, IconTicket} from "@tabler/icons-react";
import {ProductType, PromoCode, PromoCodeDiscountType} from "../../../types.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useParams} from "react-router-dom";
import {LoadingMask} from "../../common/LoadingMask";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";
import {getCurrencySymbol} from "../../../utilites/currency.ts";
import {ProductSelector} from "../../common/ProductSelector";

interface PromoCodeFormProps {
    form: UseFormReturnType<PromoCode>,
}

export const PromoCodeForm = ({form}: PromoCodeFormProps) => {
    const {eventId} = useParams();
    const {data: event, data: {product_categories: productCategories} = {}} = useGetEvent(eventId);

    const DiscountIcon = () => {
        if (form.values.discount_type === 'PERCENTAGE') {
            return <IconPercentage/>;
        }
        return getCurrencySymbol(event?.currency as string);
    };

    if (!event || !productCategories) {
        return <LoadingMask/>
    }

    return (
        <>
            <TextInput {...form.getInputProps('code')} label={t`Code`} placeholder="20OFF" required/>

            <Alert variant={'light'} mt={20} mb={20} icon={<IconAlertCircle size="1rem"/>} title={t`TIP`}>
                {t`A promo code with no discount can be used to reveal hidden products.`}
            </Alert>

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
                    leftSection={<DiscountIcon/>}
                    {...form.getInputProps('discount')}
                    label={(form.values.discount_type === 'PERCENTAGE' ? t`Discount %` : t`Discount in ${event.currency}`)}
                    placeholder="0.00"/>
            </InputGroup>

            <ProductSelector
                label={t`What products does this code apply to? (Applies to all by default)`}
                placeholder="Select products"
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
        </>
    );
};
