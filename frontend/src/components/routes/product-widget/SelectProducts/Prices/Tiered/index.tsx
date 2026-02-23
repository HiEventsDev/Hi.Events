/* eslint-disable lingui/no-unlocalized-strings */
/* eslint-disable @typescript-eslint/no-explicit-any */
import { Currency, ProductPriceDisplay } from "../../../../../common/Currency";
import { Event, Product } from "../../../../../../types.ts";
import { Group, TextInput, Modal, Button } from "@mantine/core";
import { NumberSelector } from "../../../../../common/NumberSelector";
import { useState, useEffect, useRef } from "react";
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

    const [isDonationModalOpen, setIsDonationModalOpen] = useState(false);
    const [donationModalIndex, setDonationModalIndex] = useState<number | null>(null);
    const [tempDonationAmount, setTempDonationAmount] = useState<number | ''>('');
    const [showHintIndex, setShowHintIndex] = useState<number | null>(null);

    // To detect when a quantity increments, we can use a ref to store previous quantities
    const prevQuantitiesRef = useRef<number[]>([]);

    useEffect(() => {
        if (product.type !== 'DONATION') return;

        const currentQuantities = product.prices?.map((_, index) => {
            return form.values.products?.[productIndex]?.quantities?.[index]?.quantity || 0;
        }) || [];

        let shouldUpdateRef = false;
        currentQuantities.forEach((qty, index) => {
            const prevQty = prevQuantitiesRef.current[index] || 0;
            if (qty > prevQty) {
                // Quantity increased
                setShowHintIndex(index);
                // Hide after 4 seconds
                setTimeout(() => {
                    setShowHintIndex(curr => curr === index ? null : curr);
                }, 4000);
            }
            if (qty !== prevQty) {
                shouldUpdateRef = true;
            }
        });

        if (shouldUpdateRef || prevQuantitiesRef.current.length === 0) {
            prevQuantitiesRef.current = currentQuantities;
        }
    }, [form.values.products?.[productIndex]?.quantities, product.prices, product.type, productIndex]);

    const handleOpenDonationModal = (index: number) => {
        const currentPrice = form.values.products?.[productIndex]?.quantities?.[index]?.price || product.price || 0;
        setTempDonationAmount(currentPrice);
        setDonationModalIndex(index);
        setIsDonationModalOpen(true);
        setShowHintIndex(null); // Hide hint if they clicked
    };

    const handleConfirmDonation = () => {
        if (donationModalIndex !== null && tempDonationAmount !== '' && tempDonationAmount >= (product.price || 0)) {
            form.setFieldValue(`products.${productIndex}.quantities.${donationModalIndex}.price`, tempDonationAmount);
            setIsDonationModalOpen(false);
        }
    };

    return (
        <>
            {/* Donation Dialog */}
            <Modal
                opened={isDonationModalOpen}
                onClose={() => setIsDonationModalOpen(false)}
                centered
                withCloseButton={false}
                overlayProps={{
                    color: '#000000',
                    opacity: 0.85,
                    blur: 15,
                }}
                styles={{
                    content: {
                        backgroundColor: isDarkMode ? 'rgba(20, 20, 20, 0.65)' : 'rgba(255, 255, 255, 0.85)',
                        backdropFilter: 'blur(40px)',
                        WebkitBackdropFilter: 'blur(40px)',
                        border: `1px solid ${isDarkMode ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)'}`,
                        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.7)',
                        borderRadius: '28px',
                        color: isDarkMode ? '#fff' : '#000',
                    },
                    body: {
                        padding: '40px 32px',
                    },
                    header: {
                        display: 'none',
                    }
                }}
            >
                <div className="flex flex-col items-center text-center">
                    <h3 className="text-2xl font-bold mb-2 tracking-tight">
                        {t`Custom Donation Amount`}
                    </h3>
                    <p className={`text-sm mb-8 font-medium ${isDarkMode ? 'text-white/50' : 'text-gray-500'}`}>
                        {t`Minimum amount:`} {getCurrencySymbol(event?.currency)}{product.price}
                    </p>

                    <div className="relative w-full max-w-[240px] mb-10">
                        <div className={`flex items-center justify-center text-5xl font-bold border-b-2 transition-colors pb-3 overflow-hidden ${tempDonationAmount !== '' && tempDonationAmount < (product.price || 0) ? 'border-red-500 text-red-500' : isDarkMode ? 'border-white/20 text-white focus-within:border-white' : 'border-black/10 text-black focus-within:border-black/50'}`}>
                            <span className="opacity-40 mr-1 select-none text-4xl">{getCurrencySymbol(event?.currency)}</span>
                            <input
                                type="number"
                                autoFocus
                                value={tempDonationAmount}
                                onChange={(e) => setTempDonationAmount(e.currentTarget.value ? parseFloat(e.currentTarget.value) : '')}
                                min={product.price}
                                step={0.01}
                                className="bg-transparent border-none outline-none w-32 text-center p-0 appearance-none m-0 shadow-none focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                            />
                        </div>
                        {tempDonationAmount !== '' && tempDonationAmount < (product.price || 0) && (
                            <div className="absolute -bottom-7 left-0 right-0 text-center text-red-500 text-sm font-semibold animate-pulse">
                                {t`Must be at least`} {getCurrencySymbol(event?.currency)}{product.price}
                            </div>
                        )}
                    </div>

                    <div className="flex gap-4 w-full">
                        <Button
                            variant="subtle"
                            fullWidth
                            size="lg"
                            onClick={() => setIsDonationModalOpen(false)}
                            className={`rounded-2xl transition-colors ${isDarkMode ? 'text-white hover:bg-white/10' : 'text-black hover:bg-black/5'}`}
                            styles={{
                                root: {
                                    height: '54px',
                                    fontSize: '16px',
                                    fontWeight: 600,
                                }
                            }}
                        >
                            {t`Cancel`}
                        </Button>
                        <Button
                            fullWidth
                            size="lg"
                            onClick={handleConfirmDonation}
                            disabled={tempDonationAmount === '' || typeof tempDonationAmount !== 'number' || tempDonationAmount < (product.price || 0)}
                            className="rounded-2xl transition-transform active:scale-95"
                            styles={{
                                root: {
                                    backgroundColor: isDarkMode ? '#fff' : '#000',
                                    color: isDarkMode ? '#000' : '#fff',
                                    height: '54px',
                                    fontSize: '16px',
                                    fontWeight: 600,
                                    '&:hover': {
                                        backgroundColor: isDarkMode ? '#e5e7eb' : '#333',
                                    },
                                    '&:disabled': {
                                        backgroundColor: isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
                                        color: isDarkMode ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.2)',
                                    }
                                }
                            }}
                        >
                            {t`Confirm`}
                        </Button>
                    </div>
                </div>
            </Modal>
            {product?.prices?.map((price, index) => {
                return (
                    <div key={index} className={`flex items-center justify-between gap-4 py-3 border-b ${borderClass} last:border-0 relative z-10`}>
                        <Group justify={'space-between'} wrap={'nowrap'} className="flex-1">
                            <div className="flex flex-col">
                                {price.label && <div className={`text-sm font-semibold ${textPrimaryClass}`}>{price.label}</div>}
                                <div className={`text-sm ${textSecondaryClass} mt-0.5 font-medium`}>
                                    {product.type === 'DONATION' && (
                                        <div className="mt-5 text-sm relative inline-block">
                                            <div
                                                className={`absolute left-1/2 -translate-x-1/2 bottom-[calc(100%+8px)] z-50 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold whitespace-nowrap shadow-lg transition-all duration-300 ease-in-out border ${showHintIndex === index
                                                    ? 'opacity-100 translate-y-0 scale-100'
                                                    : 'opacity-0 translate-y-2 scale-95 pointer-events-none'
                                                    } ${isDarkMode ? 'bg-black/90 text-white border-white/10 backdrop-blur-md' : 'bg-white text-gray-900 border-gray-200 shadow-xl'}`}
                                            >
                                                {t`Click here to edit donation`}
                                                {/* Tooltip Arrow */}
                                                <div className={`absolute left-1/2 -translate-x-1/2 -bottom-[5px] w-2 h-2 rotate-45 border-r border-b ${isDarkMode ? 'bg-black/90 border-white/10' : 'bg-white border-gray-200'}`}></div>
                                            </div>

                                            <div
                                                className={`group inline-flex items-center gap-1.5 cursor-pointer transition-opacity hover:opacity-70 ${textPrimaryClass}`}
                                                onClick={() => handleOpenDonationModal(index)}
                                            >
                                                <span className="text-base font-bold border-b border-dashed border-current pb-0.5">
                                                    {getCurrencySymbol(event?.currency)}
                                                    {form.values.products?.[productIndex]?.quantities?.[index]?.price || product.price || 0}
                                                </span>
                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-3.5 h-3.5 opacity-50 group-hover:opacity-100 transition-opacity" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" fill="none" strokeLinecap="round" strokeLinejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                    <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4"></path>
                                                    <path d="M13.5 6.5l4 4"></path>
                                                </svg>
                                            </div>
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
