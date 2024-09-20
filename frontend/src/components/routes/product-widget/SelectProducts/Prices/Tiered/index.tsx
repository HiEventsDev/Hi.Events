import {Currency, ProductPriceDisplay} from "../../../../../common/Currency";
import {Event, Product, ProductPriceType} from "../../../../../../types.ts";
import {Group, TextInput} from "@mantine/core";
import {NumberSelector, SharedValues} from "../../../../../common/NumberSelector";
import {UseFormReturnType} from "@mantine/form";
import {t} from "@lingui/macro";
import {ProductPriceAvailability} from "../../../../../common/ProductPriceAvailability";

interface TieredPricingProps {
    event: Event;
    product: Product;
    form: UseFormReturnType<any>;
    productIndex: number;
}

export const TieredPricing = ({product, event, form, productIndex}: TieredPricingProps) => {

    const sharedValues = new SharedValues(Math.min(product.max_per_order ?? 100, product.quantity_available ?? 10000));
    return (
        <>
            {product?.prices?.map((price, index) => {
                return (
                    <div key={index} className={'hi-price-tier-row'}>
                        <Group justify={'space-between'}>
                            <div className={'hi-price-tier'}>
                                <div className={'hi-price-tier-label'}>{price.label}</div>
                                <div className={'hi-price-tier-price'}>
                                    {product.type === 'DONATION' && (
                                        <div
                                            className={'hi-donation-input-wrapper'}>
                                            <TextInput
                                                {...form.getInputProps(`products.${productIndex}.quantities.${index}.price`)}
                                                type={'number'}
                                                min={product.price}
                                                step={0.01}
                                                placeholder={'0.00'}
                                                label={t`Amount`}
                                                required={true}
                                                w={150}
                                                mb={0}
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
                                            taxAndServiceFeeDisplayType={event?.settings?.price_display_mode}
                                        />
                                    )}
                                </div>
                            </div>
                            <div className={'hi-product-quantity-selector'}>
                                {(product.is_available && price.is_available) && (
                                    <>
                                        <NumberSelector
                                            className={'hi-product-quantity-selector'}
                                            min={product.min_per_order ?? 0}
                                            max={(Math.min(price.quantity_remaining ?? 50, product.max_per_order ?? 50))}
                                            fieldName={`products.${productIndex}.quantities.${index}.quantity`}
                                            formInstance={form}
                                            sharedValues={sharedValues}
                                        />
                                        {form.errors[`products.${productIndex}.quantities.${index}.quantity`] && (
                                            <div className={'hi-product-quantity-error'}>
                                                {form.errors[`products.${productIndex}.quantities.${index}.quantity`]}
                                            </div>
                                        )}
                                    </>
                                )}
                                {(!product.is_available || !price.is_available) && (
                                    <ProductPriceAvailability product={product} price={price} event={event}/>
                                )}
                            </div>
                        </Group>

                        {price.is_discounted && (
                            <div style={{textDecoration: 'line-through', fontSize: '.9em'}}>
                                <Currency
                                    price={price.price_before_discount}
                                    currency={event?.currency}
                                    className={'hi-price-tier-price-amount'}
                                />
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
}
