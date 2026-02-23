/* eslint-disable lingui/no-unlocalized-strings */
/* eslint-disable @typescript-eslint/no-explicit-any */
import { Currency, ProductPriceDisplay } from "../../../../../common/Currency";
import { Event, Product } from "../../../../../../types.ts";
import { Group, TextInput } from "@mantine/core";
import { NumberSelector } from "../../../../../common/NumberSelector";
import { UseFormReturnType } from "@mantine/form";
import { t } from "@lingui/macro";
import { ProductPriceAvailability } from "../../../../../common/ProductPriceAvailability";
import { getCurrencySymbol } from "../../../../../../utilites/currency.ts";

interface TieredPricingProps {
    event: Event;
    product: Product;
    form: UseFormReturnType<any>; // Need 'any' here due to Mantine form typing complexity with nested arrays, but we will ignore the warning
    productIndex: number;
    colors?: {
        primary?: string;
        primaryText?: string;
        secondaryText?: string;
    } | undefined;
}

export const TieredPricing = ({ event, product, form, productIndex, colors }: TieredPricingProps) => {
    const isDarkMode = colors?.primaryText === '#ffffff';
    const textPrimaryClass = isDarkMode ? 'text-white' : 'text-gray-900';
    const textSecondaryClass = isDarkMode ? 'text-gray-300' : 'text-gray-500';
    const borderClass = isDarkMode ? 'border-white/10' : 'border-gray-100';
    const mutedBgClass = isDarkMode ? 'bg-white/10' : 'bg-gray-100';

    return (
        <>
            {product?.prices?.map((price, index) => {
                return (
                    <div key={index} className={`flex items-center justify-between gap-4 py-3 border-b ${borderClass} last:border-0 relative z-10`}>
                        <Group justify={'space-between'} wrap={'nowrap'} className="flex-1">
                            <div className="flex flex-col">
                                {price.label && <div className={`text-sm font-semibold ${textPrimaryClass}`}>{price.label}</div>}
                                <div className={`text-sm ${textSecondaryClass} mt-0.5 font-medium`}>
                                    {product.type === 'DONATION' && (
                                        <div className="mt-2.5">
                                            <TextInput
                                                {...form.getInputProps(`products.${productIndex}.quantities.${index}.price`)}
                                                type={'number'}
                                                min={product.price}
                                                step={0.01}
                                                placeholder={t`Amount`}
                                                required={true}
                                                w={140}
                                                mb={0}
                                                leftSection={<span className="text-gray-500 font-medium text-sm">{getCurrencySymbol(event?.currency)}</span>}
                                                leftSectionPointerEvents="none"
                                                classNames={{
                                                    input: `h-10 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary font-medium shadow-sm transition-colors ${isDarkMode ? 'border-white/10 text-white bg-black/20 hover:border-white/20' : 'border-gray-200 text-gray-900 bg-gray-50 hover:border-gray-300'}`,
                                                }}
                                                styles={colors?.primary ? {
                                                    input: {
                                                        '--input-focus-bd': colors.primary,
                                                        '--input-focus-ring': colors.primary,
                                                    } as React.CSSProperties
                                                } : {}}
                                            />
                                        </div>
                                    )}
                                    {product.type !== 'DONATION' && (
                                        <ProductPriceDisplay
                                            price={price}
                                            product={product}
                                            currency={event?.currency}
                                            className={`${textPrimaryClass} font-medium`}
                                            freeLabel={t`Free`}
                                            taxAndServiceFeeDisplayType={event?.settings?.price_display_mode}
                                        />
                                    )}
                                    {price.is_discounted && (
                                        <div className="text-gray-400 line-through text-xs ml-2 inline-block">
                                            <Currency
                                                price={price.price_before_discount}
                                                currency={event?.currency}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="shrink-0 flex items-center justify-end min-w-[100px]">
                                {(product.is_available && price.is_available) && (
                                    <>
                                        <NumberSelector
                                            className={'hi-product-quantity-selector'}
                                            min={product.min_per_order ?? 0}
                                            max={(Math.min(price.quantity_remaining ?? 50, product.max_per_order ?? 50))}
                                            fieldName={`products.${productIndex}.quantities.${index}.quantity`}
                                            formInstance={form}
                                            color={colors?.primary}
                                            textColor={isDarkMode ? '#ffffff' : '#111827'}
                                            style={{ color: isDarkMode ? '#ffffff' : '#111827' }}
                                        />
                                        {form.errors[`products.${productIndex}.quantities.${index}.quantity`] && (
                                            <div className="text-red-500 text-xs mt-1 absolute right-0 -bottom-4 whitespace-nowrap">
                                                {form.errors[`products.${productIndex}.quantities.${index}.quantity`]}
                                            </div>
                                        )}
                                    </>
                                )}
                                {(!product.is_available || !price.is_available) && (
                                    <span className={`text-sm font-medium ${textSecondaryClass} ${mutedBgClass} px-3 py-1.5 rounded-lg whitespace-nowrap`}>
                                        <ProductPriceAvailability product={product} price={price} event={event} />
                                    </span>
                                )}
                            </div>
                        </Group>
                    </div>
                );
            })}
        </>
    );
}
