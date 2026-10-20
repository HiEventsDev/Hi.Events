import {UseFormReturnType} from "@mantine/form";
import {NumberInput, Radio, SegmentedControl, Switch, Textarea, TextInput} from "@mantine/core";
import {IconCash, IconReceiptTax} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useId} from "react";
import {TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType} from "../../../types.ts";
import classes from "./TaxAndFeeForm.module.scss";

const EXAMPLE_PRICE = 50;

export const TaxAndFeeForm = ({form}: { form: UseFormReturnType<TaxAndFee> }) => {
    const defaultSwitchId = useId();
    const isTax = form.values.type === TaxAndFeeType.Tax;
    const isPercentage = form.values.calculation_type === TaxAndFeeCalculationType.Percentage;
    const rate = Number(form.values.rate) || 0;

    const typeOptions = [
        {
            value: TaxAndFeeType.Tax,
            icon: IconReceiptTax,
            label: t`Tax`,
            description: t`VAT, GST or sales tax`,
        },
        {
            value: TaxAndFeeType.Fee,
            icon: IconCash,
            label: t`Fee`,
            description: t`Booking or service fee`,
        },
    ];

    const exampleAmount = (EXAMPLE_PRICE * rate / 100).toFixed(2);

    return (
        <div className={classes.form}>
            <Radio.Group
                value={form.values.type}
                onChange={(value) => form.setFieldValue('type', value as TaxAndFeeType)}
                label={t`Type`}
            >
                <div className={classes.typeOptions}>
                    {typeOptions.map(option => (
                        <Radio.Card
                            key={option.value}
                            value={option.value}
                            className={classes.typeOption}
                            data-testid={`tax-or-fee-type-${option.value.toLowerCase()}`}
                        >
                            <option.icon size={20} stroke={1.75} className={classes.typeIcon}/>
                            <span className={classes.typeText}>
                                <span className={classes.typeLabel}>{option.label}</span>
                                <span className={classes.typeDescription}>{option.description}</span>
                            </span>
                            <Radio.Indicator size="sm" className={classes.typeIndicator}/>
                        </Radio.Card>
                    ))}
                </div>
            </Radio.Group>

            <TextInput
                {...form.getInputProps('name')}
                label={t`Name`}
                placeholder={isTax ? t`VAT` : t`Service fee`}
                required
                data-autofocus
            />

            <div>
                <div className={classes.amountRow}>
                    <NumberInput
                        {...form.getInputProps('rate')}
                        className={classes.amountInput}
                        label={t`Rate`}
                        placeholder={isPercentage ? '23' : '2.50'}
                        decimalScale={2}
                        step={0.5}
                        min={0}
                        max={isPercentage ? 100 : undefined}
                        hideControls
                        rightSection={isPercentage ? '%' : undefined}
                        required
                    />
                    <SegmentedControl
                        className={classes.calculationToggle}
                        value={form.values.calculation_type}
                        onChange={(value) => form.setFieldValue('calculation_type', value as TaxAndFeeCalculationType)}
                        data={[
                            {value: TaxAndFeeCalculationType.Percentage, label: t`Percentage`},
                            {value: TaxAndFeeCalculationType.Fixed, label: t`Fixed amount`},
                        ]}
                        aria-label={t`Calculation type`}
                        data-testid="tax-or-fee-calculation-type"
                    />
                </div>
                <div className={classes.preview}>
                    {isPercentage
                        ? (rate > 0
                            ? t`On a product priced at 50.00, this adds ${exampleAmount}.`
                            : t`Charged as a percentage of each product's price.`)
                        : t`Charged per product, in the event's currency.`}
                </div>
            </div>

            <Textarea
                {...form.getInputProps('description')}
                label={t`Description`}
                description={t`Not shown to buyers.`}
                autosize
                minRows={2}
            />

            <div className={classes.defaultToggle}>
                <label className={classes.defaultText} htmlFor={defaultSwitchId}>
                    <span className={classes.defaultLabel}>{t`Add to new products automatically`}</span>
                    <span className={classes.defaultDescription}>{t`You can still remove it from any product.`}</span>
                </label>
                <Switch id={defaultSwitchId} {...form.getInputProps('is_default', {type: 'checkbox'})}/>
            </div>
        </div>
    );
};
