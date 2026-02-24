/* eslint-disable lingui/no-unlocalized-strings */
import { t, Trans } from "@lingui/macro";
import {
    ActionIcon,
    Anchor,
    Button,
    Collapse,
    Group,
    Input,
    Modal,
    Spoiler,
    TextInput,
    UnstyledButton
} from "@mantine/core";
import { useNavigate, useParams } from "react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import {
    orderClientPublic,
    ProductFormPayload,
    ProductFormValue,
    ProductPriceQuantityFormValue
} from "../../../../api/order.client.ts";
import { useForm } from "@mantine/form";
import { range, useInputState, useResizeObserver } from "@mantine/hooks";
import React, { useEffect, useMemo, useRef, useState } from "react";
import { showError, showInfo, showSuccess } from "../../../../utilites/notifications.tsx";
import { addQueryStringToUrl, isObjectEmpty, removeQueryStringFromUrl } from "../../../../utilites/helpers.ts";
import { TieredPricing } from "./Prices/Tiered";
import classNames from 'classnames';
import '../../../../styles/widget/default.scss';
import { ProductAvailabilityMessage } from "../../../common/ProductPriceAvailability";
import { PoweredByFooter } from "../../../common/PoweredByFooter";
import { Event, Product } from "../../../../types.ts";
import { eventsClientPublic } from "../../../../api/event.client.ts";
import { promoCodeClientPublic } from "../../../../api/promo-code.client.ts";
import { IconChevronRight, IconX } from "@tabler/icons-react"
import { getSessionIdentifier } from "../../../../utilites/sessionIdentifier.ts";
import { Constants } from "../../../../constants.ts";

const AFFILIATE_EXPIRY_DAYS = 30;

const sendHeightToIframeWidgets = () => {
    const height = document.documentElement.scrollHeight;
    const widgetHeight = document.querySelector('.hi-product-widget-container')?.getBoundingClientRect().height || 0;
    const urlParams = new URLSearchParams(window.location.search);
    const iframeId = urlParams.get('iframeId');

    const finalHeight = Math.max(height, widgetHeight);

    if (!iframeId) {
        return;
    }

    window.parent.postMessage({
        type: 'resize',
        height: finalHeight,
        iframeId: iframeId
    }, '*');
};

interface SelectProductsProps {
    event: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
    backgroundType?: 'color' | 'gradient' | 'image';
    colors?: {
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
        background?: string;
        bodyBackground?: string;
    },
    padding?: string;
    continueButtonText?: string;
    widgetMode?: 'preview' | 'normal' | 'embedded';
    showPoweredBy?: boolean;
}

const SelectProducts = (props: SelectProductsProps) => {
    const { eventId } = useParams();
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const promoRef = useRef<HTMLInputElement>(null);
    const [showPromoCodeInput, setShowPromoCodeInput] = useInputState<boolean>(false);
    const [event, setEvent] = useState(props.event);
    const [orderInProcessOverlayVisible, setOrderInProcessOverlayVisible] = useState(false);
    const [resizeRef, resizeObserverRect] = useResizeObserver();
    const [collapsedProducts, setCollapsedProducts] = useState<{ [key: number]: boolean }>({});
    const [affiliateCode, setAffiliateCode] = useState<string | null>(null);

    useEffect(() => sendHeightToIframeWidgets(), [resizeObserverRect.height]);

    useEffect(() => {
        const storageKey = 'affiliate_code_' + eventId;

        const now = Date.now();
        const affiliateCodeFromUrl = new URLSearchParams(window.location.search).get('aff');

        if (affiliateCodeFromUrl) {
            const data = { code: affiliateCodeFromUrl, timestamp: now };
            localStorage.setItem(storageKey, JSON.stringify(data));
            setAffiliateCode(affiliateCodeFromUrl);
            return;
        }

        const storedData = localStorage.getItem(storageKey);
        if (storedData) {
            try {
                const parsed = JSON.parse(storedData);
                const ageInDays = (now - parsed.timestamp) / (1000 * 60 * 60 * 24);
                if (ageInDays <= AFFILIATE_EXPIRY_DAYS) {
                    setAffiliateCode(parsed.code);
                } else {
                    localStorage.removeItem(storageKey);
                }
            } catch {
                localStorage.removeItem(storageKey);
            }
        }
    }, []);

    useEffect(() => {
        form.setFieldValue('affiliate_code', affiliateCode || null);
    }, [affiliateCode]);

    const form = useForm<ProductFormPayload>({
        initialValues: {
            products: undefined,
            promo_code: props.promoCodeValid ? props.promoCode || null : null,
            affiliate_code: affiliateCode || null,
            session_identifier: undefined,
        },
    });

    const productMutation = useMutation({
        mutationFn: (orderData: ProductFormPayload) => orderClientPublic.create(Number(eventId), orderData),

        onSuccess: (data) => queryClient.invalidateQueries()
            .then(() => {
                const url = '/checkout/' + eventId + '/' + data.data.short_id + '/details';
                if (props.widgetMode === 'embedded') {
                    window.open(
                        url + '?session_identifier=' + data.data.session_identifier + '&utm_source=embedded_widget',
                        '_blank'
                    );
                    setOrderInProcessOverlayVisible(true);
                    return;
                }

                return navigate(url);
            }),

        onError: (error: any) => {
            if (error?.response?.data?.errors) {
                form.setErrors(error.response.data.errors);
            }

            notifications.show({
                message: error.response.data.errors?.products[0] || t`Unable to create product. Please check your details`,
                color: 'red',
            });
        }
    });

    const promoCodeEventRefetchMutation = useMutation({
        mutationFn: async (promoCode: string | null) => {
            if (promoCode) {
                const validPromoCode = await promoCodeClientPublic.validateCode(
                    eventId,
                    promoCode
                );

                if (!validPromoCode.valid) {
                    showError(t`That promo code is invalid`);
                    return;
                }
            }

            const eventWithPromoCodeApplied = await eventsClientPublic.findByID(
                eventId,
                promoCode
            );

            setEvent(eventWithPromoCodeApplied.data);

            if (promoCode) {
                form.setFieldValue("promo_code", promoCode);
            } else {
                form.setFieldValue("promo_code", null);
                setShowPromoCodeInput(false)
                removeQueryStringFromUrl('promo_code');
            }
        },
    });

    const productCategories = event?.product_categories || [];
    const productAreAvailable = productCategories && productCategories.some(category => !!category?.products?.length);
    const products: Product[] = productCategories.reduce((acc: Product[], category) => acc.concat(category.products ?? []), []);

    const selectedProductQuantitySum = useMemo(() => {
        let total = 0;
        form.values.products?.forEach(({ quantities }) => {
            quantities?.forEach(({ quantity }) => {
                total += Number(quantity);
            });
        });

        return total;
    }, [form.values.products]);

    useEffect(() => {
        if (form.values.promo_code) {
            const promo_code = form.values.promo_code;
            showSuccess(t`Promo ${promo_code} code applied`);
            addQueryStringToUrl('promo_code', promo_code);
        }
    }, [form.values.promo_code])

    useEffect(() => {
        if (typeof props.promoCodeValid !== 'undefined') {
            if (!props.promoCodeValid) {
                showError(t`That promo code is invalid`);
                removeQueryStringFromUrl('promo_code');
            }
        }
    }, [props.promoCodeValid])

    const populateFormValue = () => {
        const productValues: Array<ProductFormValue> = [];
        products?.forEach(product => {
            const quantitiesValues: Array<ProductPriceQuantityFormValue> = [];

            const existingProduct = form.values.products?.find(p => p.product_id === product.id);

            product.prices?.forEach(priceQuantity => {
                const existingQuantity = existingProduct?.quantities?.find(q => q.price_id === priceQuantity.id)?.quantity || 0;

                quantitiesValues.push({
                    quantity: existingQuantity,
                    price_id: Number(priceQuantity.id),
                    price: product.type === 'DONATION' ? Number(priceQuantity.price) : undefined,
                });
            });

            if (quantitiesValues.length === 0) {
                quantitiesValues.push({
                    quantity: 0,
                    price_id: 0,
                    price: 0,
                });
            }

            productValues.push({
                product_id: Number(product.id),
                quantities: quantitiesValues,
            });
        });

        if (JSON.stringify(form.values.products) !== JSON.stringify(productValues)) {
            form.setFieldValue("products", productValues);
        }
    };

    useEffect(populateFormValue, [productCategories]);

    const handleProductSelection = (values: Omit<ProductFormPayload, "session_identifier">) => {
        if (values && selectedProductQuantitySum > 0) {
            productMutation.mutate({
                ...values,
                session_identifier: getSessionIdentifier()
            });
        } else {
            showInfo(t`Please select at least one product`);
        }
    };

    const handleApplyPromoCode = () => {
        const promoCode = promoRef.current?.value;
        if (promoCode && promoCode.length >= 3) {
            promoCodeEventRefetchMutation.mutate(promoCode);
        } else {
            showError(t`Sorry, this promo code is not recognized`);
        }
    }

    const isButtonDisabled = productMutation.isPending
        || !productAreAvailable
        || selectedProductQuantitySum === 0
        || props.widgetMode === 'preview'
        || products?.every(product => product.is_sold_out);

    const isDarkMode = props.colors?.primaryText === '#ffffff';
    const cardBgClass = isDarkMode ? 'bg-black/20 backdrop-blur-2xl border-white/10 hover:bg-black/30 hover:border-white/20 shadow-xl' : 'bg-white/40 backdrop-blur-2xl border-white/50 hover:border-white/70 shadow-xl';
    const textPrimaryClass = isDarkMode ? 'text-white' : 'text-gray-900';
    const textSecondaryClass = isDarkMode ? 'text-gray-300' : 'text-gray-500';
    const mutedBgClass = isDarkMode ? 'bg-black/20 backdrop-blur-xl border-white/10' : 'bg-white/30 backdrop-blur-xl border-white/40';
    const highlightCardClass = isDarkMode ? 'border-primary bg-primary/20 backdrop-blur-2xl shadow-[0_0_15px_rgba(var(--theme-accent-rgb),0.1)] ring-1 ring-primary/50' : 'border-primary bg-primary/10 backdrop-blur-2xl shadow-xl ring-1 ring-primary/50';

    let productIndex = 0;

    return (
        <div
            ref={resizeRef}
            className={classNames(['hi-widget-container', 'select-products', props.widgetMode])}
            style={{
                '--widget-background-color': props.colors?.background,
                '--widget-primary-color': props.colors?.primary,
                '--widget-primary-text-color': props.colors?.primaryText,
                '--widget-secondary-color': props.colors?.secondary,
                '--widget-secondary-text-color': props.colors?.secondaryText,
                '--widget-padding': props?.padding,
            } as React.CSSProperties}>
            {!productAreAvailable && (
                <div className={classNames(['hi-no-products'])}>
                    <p className={classNames(['hi-no-products-message'])} style={{ color: props.colors?.primaryText }}>
                        {t`There are no products available for this event`}
                    </p>
                </div>
            )}
            {orderInProcessOverlayVisible && (
                <Modal
                    withCloseButton={false}
                    opened={true}
                    onClose={() => setOrderInProcessOverlayVisible(false)}
                    styles={{
                        body: {
                            padding: '30px 24px'
                        },
                        content: {
                            borderRadius: '8px',
                            backgroundColor: props.colors?.background || 'white'
                        }
                    }}
                >
                    <div style={{
                        textAlign: 'center',
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: '16px',
                        color: props.colors?.primaryText || 'inherit'
                    }}>
                        <div style={{ width: '100%' }}>
                            <h3 style={{
                                margin: '0 0 12px 0',
                                fontSize: '20px',
                                fontWeight: '600',
                                color: props.colors?.primaryText || 'inherit'
                            }}>
                                {t`Please continue in the new tab`}
                            </h3>

                            <p style={{
                                margin: '0 0 20px 0',
                                fontSize: '15px',
                                lineHeight: '1.5',
                                color: props.colors?.primaryText || 'inherit'
                            }}>
                                {t`If a new tab did not open automatically, please click the button below to continue to checkout.`}
                            </p>

                            <Button
                                component="a"
                                href={'/checkout/' + eventId + '/' + productMutation.data?.data.short_id + '/details' + '?session_identifier=' + productMutation.data?.data.session_identifier}
                                target={'_blank'}
                                rel={'noopener noreferrer'}
                                fullWidth
                                size="md"
                                styles={{
                                    root: {
                                        backgroundColor: props.colors?.secondary || 'var(--primary-color, #228be6)',
                                        color: props.colors?.secondaryText || 'var(--accent-contrast, white)',
                                        fontWeight: 600,
                                        marginBottom: '12px',
                                        '&:hover': {
                                            backgroundColor: props.colors?.secondary || 'var(--primary-color, #1c7ed6)',
                                            filter: 'brightness(0.95)',
                                        }
                                    }
                                }}
                            >
                                {t`Continue to Checkout`}
                            </Button>

                            <Button
                                onClick={() => setOrderInProcessOverlayVisible(false)}
                                variant={'subtle'}
                                size={'sm'}
                                className={textPrimaryClass}
                                styles={{
                                    root: {
                                        '&:hover': {
                                            backgroundColor: 'transparent',
                                            textDecoration: 'underline'
                                        }
                                    }
                                }}
                            >
                                {t`Dismiss this message`}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
            {(event && productAreAvailable) && (
                <>
                    <form target={'__blank'} onSubmit={form.onSubmit(handleProductSelection as any)}>
                        <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                        <Input type={'hidden'} {...form.getInputProps('affiliate_code')} />
                        <div className={'hi-product-category-rows'}>
                            {productCategories && productCategories.map((category) => {
                                return (
                                    <div className="relative z-10 space-y-4 mb-8" key={category.id}>
                                        <h2 className={`text-xl font-bold ${textPrimaryClass} tracking-tight`} style={category.description ? {
                                            marginBottom: '0.5rem'
                                        } : { marginBottom: '0.5rem' }}>
                                            {category.name}
                                        </h2>
                                        {category.description && (
                                            <div className={`text-sm mb-4 prose prose-sm max-w-none ${isDarkMode ? 'prose-invert text-gray-300 prose-headings:text-white prose-a:text-white hover:prose-a:text-gray-200' : 'text-gray-500 prose-headings:text-gray-900 prose-a:text-gray-900 hover:prose-a:text-gray-700'}`}>
                                                <Spoiler styles={{ control: { color: isDarkMode ? '#e5e7eb' : '#374151' } }} maxHeight={500} showLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Show more`}</span>} hideLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Hide`}</span>}>
                                                    <div dangerouslySetInnerHTML={{ __html: category.description }} />
                                                </Spoiler>
                                            </div>
                                        )}
                                        <div className="space-y-3">
                                            {category.products?.length === 0 && (
                                                <div className={`${mutedBgClass} border rounded-2xl p-6 text-center`}>
                                                    <p className={`${textSecondaryClass} font-medium`}>
                                                        {category.no_products_message || t`There are no products available in this category`}
                                                    </p>
                                                </div>
                                            )}

                                            {(category.products) && category.products.map((product) => {
                                                const currentProductIndex = productIndex;
                                                const quantityRange = range(product.min_per_order || 1, product.max_per_order || 25)
                                                    .map((n) => n.toString());
                                                quantityRange.unshift("0");

                                                const isProductCollapsed = collapsedProducts[Number(product.id)] ?? product.start_collapsed;
                                                const toggleCollapse = () => {
                                                    setCollapsedProducts(prev => ({
                                                        ...prev,
                                                        [Number(product.id)]: !isProductCollapsed
                                                    }));
                                                };

                                                const currentProductQuantities = form.values.products?.[currentProductIndex]?.quantities || [];
                                                const productTotalQuantity = currentProductQuantities.reduce((acc, { quantity }) => acc + Number(quantity), 0);
                                                const isSelected = productTotalQuantity > 0;
                                                const hasMultiplePrices = (product.prices?.length ?? 0) > 1;

                                                return (
                                                    <div key={product.id} className={`group relative rounded-2xl border transition-all duration-200 p-5 ${isSelected ? highlightCardClass : cardBgClass} ${product.is_highlighted ? 'border-primary ring-1 ring-primary/30' : ''}`}>
                                                        {product.is_highlighted && product.highlight_message && (
                                                            <div className={`absolute -top-3 left-4 text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm border ${isDarkMode ? 'bg-black border-white text-white' : 'bg-white border-gray-200 text-gray-900'}`}>
                                                                {product.highlight_message}
                                                            </div>
                                                        )}

                                                        <div className="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                                            <div className={`flex-1 ${hasMultiplePrices ? 'cursor-pointer' : ''}`} onClick={hasMultiplePrices ? toggleCollapse : undefined}>
                                                                <h3 className={`text-lg font-bold ${textPrimaryClass} tracking-tight`}>
                                                                    {product.title}
                                                                </h3>

                                                                <div className="flex items-center gap-3 mt-0 flex-wrap">
                                                                    {(product.is_available && !!product.quantity_available) && (
                                                                        <div className={`text-sm font-medium ${textSecondaryClass} ${isDarkMode ? 'bg-white/10' : 'bg-gray-100'} px-2 py-0.5 rounded-md`}>
                                                                            {product.quantity_available === Constants.INFINITE_TICKETS && (
                                                                                <Trans>Unlimited available</Trans>
                                                                            )}
                                                                            {product.quantity_available !== Constants.INFINITE_TICKETS && (
                                                                                <Trans>{product.quantity_available} available</Trans>
                                                                            )}
                                                                        </div>
                                                                    )}

                                                                    {(!product.is_available && product.type === 'TIERED') && (
                                                                        <div className={`text-sm font-medium ${textSecondaryClass} ${isDarkMode ? 'bg-white/10' : 'bg-gray-100'} px-2 py-0.5 rounded-md`}>
                                                                            <ProductAvailabilityMessage product={product} event={event} />
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                {!hasMultiplePrices && product.description && (
                                                                    <div className={`mt-0 text-sm prose prose-sm max-w-none ${isDarkMode ? 'prose-invert text-gray-300 prose-headings:text-white prose-a:text-white hover:prose-a:text-gray-200' : 'text-gray-500 prose-headings:text-gray-900 prose-a:text-gray-900 hover:prose-a:text-gray-700'}`}>
                                                                        <Spoiler styles={{ control: { color: isDarkMode ? '#e5e7eb' : '#374151', marginTop: '0.5rem' } }} maxHeight={100} showLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Show more`}</span>} hideLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Hide`}</span>}>
                                                                            <div dangerouslySetInnerHTML={{ __html: product.description }} />
                                                                        </Spoiler>
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Single Price Inline Rendering */}
                                                            {!hasMultiplePrices && (
                                                                <div className="shrink-0 w-full sm:w-auto min-w-[140px]">
                                                                    <TieredPricing
                                                                        productIndex={productIndex++}
                                                                        key={product.id}
                                                                        event={event}
                                                                        product={product}
                                                                        form={form}
                                                                        colors={props.colors}
                                                                    />
                                                                </div>
                                                            )}

                                                            {/* Multiple Prices Toggle Button */}
                                                            {hasMultiplePrices && (
                                                                <div className="shrink-0 hidden sm:flex items-center justify-end">
                                                                    <UnstyledButton onClick={toggleCollapse} className={`${textSecondaryClass} ${mutedBgClass} hover:opacity-80 rounded-full p-2.5 transition-colors border`}>
                                                                        <IconChevronRight size={18} className={`transition-transform duration-200 ${!isProductCollapsed ? 'rotate-90' : ''}`} />
                                                                    </UnstyledButton>
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Multiple Prices Expanded View & Description */}
                                                        {hasMultiplePrices && (
                                                            <Collapse transitionDuration={200} in={!isProductCollapsed} hidden={isProductCollapsed}>
                                                                <div className={`mt-4 pt-4 border-t ${isDarkMode ? 'border-white/10' : 'border-gray-100'}`}>
                                                                    <TieredPricing
                                                                        productIndex={productIndex++}
                                                                        key={product.id}
                                                                        event={event}
                                                                        product={product}
                                                                        form={form}
                                                                        colors={props.colors}
                                                                    />

                                                                    {product.description && (
                                                                        <div className={`mt-4 text-sm prose prose-sm max-w-none border p-4 rounded-xl ${mutedBgClass} ${isDarkMode ? 'prose-invert text-gray-300 prose-headings:text-white prose-a:text-white hover:prose-a:text-gray-200' : 'text-gray-500 prose-headings:text-gray-900 prose-a:text-gray-900 hover:prose-a:text-gray-700'}`}>
                                                                            <Spoiler styles={{ control: { color: isDarkMode ? '#e5e7eb' : '#374151', marginTop: '0.5rem' } }} maxHeight={100} showLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Show more`}</span>} hideLabel={<span className="font-semibold underline underline-offset-2 opacity-80 hover:opacity-100 transition-opacity">{t`Hide`}</span>}>
                                                                                <div dangerouslySetInnerHTML={{ __html: product.description }} />
                                                                            </Spoiler>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </Collapse>
                                                        )}

                                                        {/* Validation Errors */}
                                                        {product.max_per_order && form.values.products && isObjectEmpty(form.errors) && (currentProductQuantities.reduce((acc, { quantity }) => acc + Number(quantity), 0) > product.max_per_order) && (
                                                            <div className={`mt-3 text-red-500 text-sm font-medium p-3 rounded-xl border ${isDarkMode ? 'bg-red-500/10 border-red-500/20' : 'bg-red-50 border-red-100'}`}>
                                                                <Trans>The maximum number of products for {product.title} is {product.max_per_order}</Trans>
                                                            </div>
                                                        )}

                                                        {form.errors[`products.${currentProductIndex}`] && (
                                                            <div className={`mt-3 text-red-500 text-sm font-medium p-3 rounded-xl border ${isDarkMode ? 'bg-red-500/10 border-red-500/20' : 'bg-red-50 border-red-100'}`}>
                                                                {form.errors[`products.${currentProductIndex}`]}
                                                            </div>
                                                        )}
                                                    </div>
                                                )
                                            })}
                                        </div>
                                    </div>
                                )
                            })}
                        </div>

                        <div className={`mt-8 pt-6 border-t ${isDarkMode ? 'border-white/10' : 'border-gray-100'}`}>
                            {event?.settings?.product_page_message && (
                                <div dangerouslySetInnerHTML={{
                                    __html: event.settings.product_page_message.replace(/\n/g, '<br/>')
                                }} className="mb-6 p-4 bg-blue-50/50 border border-blue-100 rounded-xl text-sm text-blue-800 leading-relaxed font-medium" />
                            )}
                            <Button
                                disabled={isButtonDisabled}
                                fullWidth
                                size="lg"
                                className="h-14 rounded-xl font-bold shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5"
                                type="submit"
                                loading={productMutation.isPending}
                                styles={{
                                    root: {
                                        backgroundColor: props.colors?.primary || 'var(--primary-color, #228be6)',
                                        color: props.colors?.secondaryText || '#ffffff',
                                    }
                                }}
                            >
                                {props.continueButtonText || event?.settings?.continue_button_text || t`Continue`}
                            </Button>
                        </div>
                    </form>
                    <div className="mt-6 flex flex-col items-center gap-4">
                        {(!showPromoCodeInput && !form.values.promo_code) && (
                            <Anchor className={`text-sm font-medium transition-colors hover:opacity-80`} underline="never"
                                style={{ color: isDarkMode ? '#d1d5db' : '#4b5563' }}
                                onClick={() => setShowPromoCodeInput(true)}>
                                {t`Have a promo code?`}
                            </Anchor>
                        )}
                        {form.values.promo_code && (
                            <div className={`flex items-center gap-2 border px-4 py-2 rounded-xl text-sm font-medium shadow-sm ${mutedBgClass} ${textPrimaryClass}`}>
                                <span><b className={textPrimaryClass}>{form.values.promo_code}</b> {t`applied`}</span>
                                <ActionIcon
                                    className="text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors rounded-full"
                                    variant="transparent"
                                    aria-label={t`remove`}
                                    title={t`Remove`}
                                    onClick={() => {
                                        promoCodeEventRefetchMutation.mutate(null)
                                    }}
                                >
                                    <IconX stroke={2} size={16} />
                                </ActionIcon>
                            </div>
                        )}

                        {(showPromoCodeInput && !form.values.promo_code) && (
                            <Group className="flex w-full max-w-sm mx-auto items-center gap-2" wrap="nowrap" gap="10px">
                                {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                                {/*@ts-ignore*/}
                                <TextInput autoFocus classNames={{ input: `h-10 rounded-xl text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm transition-colors ${isDarkMode ? 'border-white/10 text-white bg-black/20 hover:border-white/20' : 'border-gray-200 text-gray-900 bg-gray-50 hover:border-gray-300'}` }} onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        handleApplyPromoCode();
                                    }
                                }} mb={0} ref={promoRef} className="flex-1" />
                                <Button disabled={promoCodeEventRefetchMutation.isPending}
                                    className={`h-10 rounded-xl px-4 text-sm font-medium transition-colors border shadow-sm ${isDarkMode ? 'border-white/10 bg-white/5 hover:bg-white/10' : 'border-gray-200 bg-white hover:bg-gray-50'}`} variant={'unstyled'}
                                    style={{ color: isDarkMode ? '#ffffff' : '#374151' }}
                                    onClick={handleApplyPromoCode}>
                                    {t`Apply`}
                                </Button>
                                <ActionIcon
                                    className="transition-colors rounded-full ml-1"
                                    variant="unstyled"
                                    style={{ color: isDarkMode ? '#d1d5db' : '#6b7280' }}
                                    aria-label={t`close`}
                                    title={t`Close`}
                                    onClick={() => setShowPromoCodeInput(false)}
                                >
                                    <IconX stroke={2} size={20} />
                                </ActionIcon>
                            </Group>
                        )}
                    </div>
                </>
            )}
            {
                /**
                 * (c) Hi.Events Ltd 2025
                 *
                 * PLEASE NOTE:
                 *
                 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
                 *
                 * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENCE
                 *
                 * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
                 *
                 * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
                 */
            }
            {
                (props.showPoweredBy ?? true) && (
                    <PoweredByFooter style={{
                        'color': props.colors?.primaryText || '#000',
                    }} />
                )
            }
        </div >
    );
}

export default SelectProducts;
