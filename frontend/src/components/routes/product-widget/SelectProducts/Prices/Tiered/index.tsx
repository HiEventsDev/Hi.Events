import {Currency, getExclusiveFeeNote, getInclusiveFeeNote, ProductPriceDisplay} from "../../../../../common/Currency";
import {Event, IdParam, Product, ProductPrice, TaxAndFeeType} from "../../../../../../types.ts";
import {TextInput} from "@mantine/core";
import {NumberSelector} from "../../../../../common/NumberSelector";
import {UseFormReturnType} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconClock} from "@tabler/icons-react";
import {useEffect, useRef, useState} from "react";
import {ProductPriceAvailability} from "../../../../../common/ProductPriceAvailability";
import {formatCurrency, getCurrencySymbol} from "../../../../../../utilites/currency.ts";
import {FeeBreakdown, FeeBreakdownRow} from "../FeeBreakdown";

const LIMIT_MESSAGE_TIMEOUT_MS = 4000;

interface TieredPricingProps {
    event: Event;
    product: Product;
    form: UseFormReturnType<any>;
    productIndex: number;
    eventOccurrenceId?: IdParam;
    displayMode?: 'header' | 'list';
    showStepper?: boolean;
}

const getFeesAndTaxTotal = (price: ProductPrice): number => (price.tax_total || 0) + (price.fee_total || 0);

const getAvailabilityReason = (price: ProductPrice): string => {
    if (price.is_sold_out) {
        return 'sold-out';
    }
    if (price.is_after_sale_end_date) {
        return 'ended';
    }
    if (price.is_before_sale_start_date) {
        return 'upcoming';
    }
    return 'unavailable';
};

export const TieredPricing = ({
                                  product,
                                  event,
                                  form,
                                  productIndex,
                                  eventOccurrenceId,
                                  displayMode = 'list',
                                  showStepper = true,
                              }: TieredPricingProps) => {
    const [limitMessages, setLimitMessages] = useState<{ [priceIndex: number]: string }>({});
    const limitTimeoutsRef = useRef<{ [priceIndex: number]: ReturnType<typeof setTimeout> }>({});

    useEffect(() => () => {
        Object.values(limitTimeoutsRef.current).forEach(clearTimeout);
    }, []);

    const priceDisplayMode = event?.settings?.price_display_mode;
    const isInclusive = priceDisplayMode === 'INCLUSIVE';

    const getQuantityCap = (price: ProductPrice): number =>
        Math.min(price.quantity_remaining ?? 50, product.max_per_order ?? 50);

    const flashLimitMessage = (price: ProductPrice, index: number) => {
        const cap = getQuantityCap(price);
        const limitedByStock = (price.quantity_remaining ?? Infinity) < (product.max_per_order ?? 50);
        const message = limitedByStock ? t`Only ${cap} available` : t`Maximum ${cap} per order`;

        setLimitMessages(previous => ({...previous, [index]: message}));
        clearTimeout(limitTimeoutsRef.current[index]);
        limitTimeoutsRef.current[index] = setTimeout(() => {
            setLimitMessages(previous => {
                const next = {...previous};
                delete next[index];
                return next;
            });
        }, LIMIT_MESSAGE_TIMEOUT_MS);
    };

    const exclusiveFootnote = isInclusive ? undefined : t`Added at checkout`;

    const buildSinglePriceRows = (price: ProductPrice): FeeBreakdownRow[] => {
        const feeNames = (product.taxes || []).filter(item => item.type === TaxAndFeeType.Fee).map(item => item.name).join(', ');
        const taxNames = (product.taxes || []).filter(item => item.type === TaxAndFeeType.Tax).map(item => item.name).join(', ');

        const rows: FeeBreakdownRow[] = [{label: t`Base price`, amount: Number(price.price)}];
        if ((price.fee_total || 0) > 0) {
            rows.push({label: feeNames || t`Fees`, amount: price.fee_total || 0});
        }
        if ((price.tax_total || 0) > 0) {
            rows.push({label: taxNames || t`Tax`, amount: price.tax_total || 0});
        }
        rows.push({label: t`Total`, amount: Number(price.price) + getFeesAndTaxTotal(price), isTotal: true});

        return rows;
    };

    const renderQuantityControl = (price: ProductPrice, index: number) => (
        <div className={'hi-product-quantity-selector'}>
            {(product.is_available && price.is_available) && showStepper && (
                <NumberSelector
                    min={product.min_per_order ?? 0}
                    max={getQuantityCap(price)}
                    fieldName={`products.${productIndex}.quantities.${index}.quantity`}
                    formInstance={form}
                    selectorSize={displayMode === 'list' ? 'compact' : 'default'}
                    onLimitReached={() => flashLimitMessage(price, index)}
                />
            )}
            {(!product.is_available || !price.is_available) && (
                <div className={'hi-product-availability'} data-reason={getAvailabilityReason(price)}>
                    {(price.is_before_sale_start_date || price.is_after_sale_end_date) && !price.is_sold_out && (
                        <IconClock size={14} stroke={2}/>
                    )}
                    <ProductPriceAvailability product={product} price={price} event={event}
                                              eventOccurrenceId={eventOccurrenceId}/>
                </div>
            )}
        </div>
    );

    const renderRowMessages = (index: number) => (
        <>
            {form.errors[`products.${productIndex}.quantities.${index}.quantity`] && (
                <div className={'hi-product-quantity-error'}>
                    {form.errors[`products.${productIndex}.quantities.${index}.quantity`]}
                </div>
            )}
            {limitMessages[index] && (
                <div className={'hi-product-quantity-error'}>
                    {limitMessages[index]}
                </div>
            )}
        </>
    );

    if (displayMode === 'header') {
        const price = product.prices?.[0];
        if (!price) {
            return null;
        }
        const feesAndTax = getFeesAndTaxTotal(price);
        const isPriceAvailable = product.is_available && price.is_available;

        return (
            <div className={'hi-product-header-pricing'}
                 data-unavailable={!isPriceAvailable ? getAvailabilityReason(price) : undefined}>
                <div className={'hi-price-tier-row'}>
                    <div className={'hi-price-tier'}>
                        <div className={'hi-price-tier-price'}>
                            <ProductPriceDisplay
                                price={price}
                                product={product}
                                currency={event?.currency}
                                className={'hi-price-tier-price-amount'}
                                freeLabel={t`Free`}
                                taxAndServiceFeeDisplayType={priceDisplayMode}
                                feeDisplay={'none'}
                            />
                            {price.is_discounted && (
                                <div className={'hi-price-strike'}>
                                    <Currency
                                        price={price.price_before_discount}
                                        currency={event?.currency}
                                        className={'hi-price-tier-price-amount'}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                    {renderQuantityControl(price, 0)}
                </div>
                {feesAndTax > 0 && isPriceAvailable && (
                    <FeeBreakdown
                        toggleLabel={isInclusive
                            ? getInclusiveFeeNote((price.fee_total || 0) > 0, (price.tax_total || 0) > 0)
                            : getExclusiveFeeNote(formatCurrency(feesAndTax, event?.currency), (price.fee_total || 0) > 0, (price.tax_total || 0) > 0)}
                        rows={buildSinglePriceRows(price)}
                        currency={event?.currency}
                        footnote={exclusiveFootnote}
                    />
                )}
                {renderRowMessages(0)}
            </div>
        );
    }

    return (
        <>
            {product?.prices?.map((price, index) => {
                const feesAndTax = getFeesAndTaxTotal(price);
                const isPriceAvailable = product.is_available && price.is_available;

                return (
                    <div key={index} className={'hi-price-tier-row'}
                         data-unavailable={!isPriceAvailable ? getAvailabilityReason(price) : undefined}>
                        <div className={'hi-price-tier-main'}>
                            <div className={'hi-price-tier'}>
                                {price.label && (
                                    <div className={'hi-price-tier-label'}>{price.label}</div>
                                )}
                                <div className={'hi-price-tier-price'}>
                                    {product.type === 'DONATION' && (
                                        <div className={'hi-donation-input-wrapper'}>
                                            <TextInput
                                                {...form.getInputProps(`products.${productIndex}.quantities.${index}.price`)}
                                                type={'number'}
                                                min={product.price}
                                                step={0.01}
                                                placeholder={t`Pay what you want`}
                                                aria-label={t`Amount`}
                                                required={true}
                                                mb={0}
                                                leftSection={getCurrencySymbol(event?.currency)}
                                                classNames={{
                                                    input: 'hi-donation-input',
                                                }}
                                            />
                                        </div>
                                    )}
                                    {product.type !== 'DONATION' && (
                                        <ProductPriceDisplay
                                            price={price}
                                            product={product}
                                            currency={event?.currency}
                                            className={'hi-price-tier-price-amount'}
                                            freeLabel={t`Free`}
                                            taxAndServiceFeeDisplayType={priceDisplayMode}
                                            feeDisplay={'none'}
                                        />
                                    )}
                                </div>
                            </div>
                            {renderQuantityControl(price, index)}
                        </div>

                        {product.type !== 'DONATION' && feesAndTax > 0 && isPriceAvailable && (
                            <FeeBreakdown
                                toggleLabel={isInclusive
                                    ? getInclusiveFeeNote((price.fee_total || 0) > 0, (price.tax_total || 0) > 0)
                                    : getExclusiveFeeNote(formatCurrency(feesAndTax, event?.currency), (price.fee_total || 0) > 0, (price.tax_total || 0) > 0)}
                                rows={buildSinglePriceRows(price)}
                                currency={event?.currency}
                                footnote={exclusiveFootnote}
                            />
                        )}

                        {price.is_discounted && (
                            <div className={'hi-price-strike'}>
                                <Currency
                                    price={price.price_before_discount}
                                    currency={event?.currency}
                                    className={'hi-price-tier-price-amount'}
                                />
                            </div>
                        )}

                        {renderRowMessages(index)}
                    </div>
                );
            })}
        </>
    );
}
