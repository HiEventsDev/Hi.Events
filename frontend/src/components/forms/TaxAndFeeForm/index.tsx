import {UseFormReturnType} from "@mantine/form";
import {TaxAndFee, TaxAndFeeApplicationType, TaxAndFeeCalculationType, TaxAndFeeType} from "../../../types.ts";
import {NumberInput, SegmentedControl, Switch, TextInput} from "@mantine/core";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {IconCash, IconPercentage, IconReceiptTax} from "@tabler/icons-react";
import {t} from "@lingui/macro";

export const TaxAndFeeForm = ({form}: { form: UseFormReturnType<TaxAndFee> }) => {
    const typeOptions: ItemProps[] = [
        {
            icon: <IconReceiptTax/>,
            label: t`Tax`,
            value: 'TAX',
            description: t`A standard tax, like VAT or GST`,
        },
        {
            icon: <IconCash/>,
            label: t`Fee`,
            value: 'FEE',
            description: t`A fee, like a booking fee or a service fee`,
        },
    ];

    const calcTypeOptions: ItemProps[] = [
        {
            icon: <IconPercentage/>,
            label: t`Percentage`,
            value: 'PERCENTAGE',
            description: t`A percentage of the product price. E.g., 3.5% of the product price`,
        },
        {
            icon: <IconCash/>,
            label: t`Fixed`,
            value: 'FIXED',
            description: t`A fixed amount per product. E.g, $0.50 per product`,
        },
    ];

    const type = (form.values.type === TaxAndFeeType.Tax ? t`Tax` : t`Fee`).toLowerCase();

    return (
        <div>
            <CustomSelect
                label={t`Type`}
                required
                form={form}
                name={'type'}
                optionList={typeOptions}
            />

            <CustomSelect
                label={t`Calculation Type`}
                required
                form={form}
                name={'calculation_type'}
                optionList={calcTypeOptions}
            />

            <TextInput
                {...form.getInputProps('name')}
                label={t`Name`}
                placeholder={form.values.type === TaxAndFeeType.Tax ? t`VAT` : t`Service Fee`}
                required
            />

            <NumberInput
                decimalScale={2}
                fixedDecimalScale
                step={0.50}
                {...form.getInputProps('rate')}
                label={form.values.calculation_type === TaxAndFeeCalculationType.Percentage ? t`Percentage Amount` : t`Amount`}
                placeholder={form.values.calculation_type === TaxAndFeeCalculationType.Percentage ? '23' : '2.50'}
                leftSection={form.values.calculation_type === TaxAndFeeCalculationType.Percentage ? '%' : ''}
                description={form.values.calculation_type === TaxAndFeeCalculationType.Percentage ? t`eg. 23.5 for 23.5%` : t`eg. 2.50 for $2.50`}
                required
                max={form.values.calculation_type === TaxAndFeeCalculationType.Percentage ? 100 : undefined}
            />

            <TextInput
                {...form.getInputProps('description')}
                label={t`Description`}
            />

            <Switch
                {...form.getInputProps('is_default', {type: 'checkbox'})}
                label={t`Apply this ${type} to all new products`}
                value={1}
                description={t`A default ${type} is automaticaly applied to all new products. You can override this on a per product basis.`}
            />

            <Switch
                {...form.getInputProps('is_online_only', {type: 'checkbox'})}
                label={t`Online payments only`}
                value={1}
                description={t`When enabled, this ${type} will only be applied to online payments (e.g., Stripe). It will not be charged for offline payments.`}
            />

            {form.values.type === TaxAndFeeType.Tax && (
                <Switch
                    {...form.getInputProps('is_tax_inclusive', {type: 'checkbox'})}
                    label={t`Tax inclusive pricing`}
                    value={1}
                    description={t`When enabled, product prices are treated as already including this tax. The tax will be extracted from the price for reporting, rather than added on top.`}
                />
            )}

            <div style={{marginTop: 10}}>
                <label style={{fontSize: '14px', fontWeight: 500, display: 'block', marginBottom: 5}}>
                    {t`Application`}
                </label>
                <SegmentedControl
                    fullWidth
                    data={[
                        {label: t`Per Product`, value: TaxAndFeeApplicationType.PerProduct},
                        {label: t`Per Order`, value: TaxAndFeeApplicationType.PerOrder},
                    ]}
                    {...form.getInputProps('application_type')}
                />
                <div style={{fontSize: '12px', color: 'var(--mantine-color-dimmed)', marginTop: 4}}>
                    {form.values.application_type === TaxAndFeeApplicationType.PerOrder
                        ? t`This ${type} will be applied once per order, regardless of the number of products.`
                        : t`This ${type} will be applied to each product individually.`
                    }
                </div>
            </div>
        </div>
    )
}
