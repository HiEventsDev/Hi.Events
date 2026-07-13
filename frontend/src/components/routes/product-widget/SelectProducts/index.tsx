import {t, Trans} from "@lingui/macro";
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
import {useNavigate, useParams} from "react-router";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {
    orderClientPublic,
    ProductFormPayload,
    ProductFormValue,
    ProductPriceQuantityFormValue
} from "../../../../api/order.client.ts";
import {useForm} from "@mantine/form";
import {range, useInputState, useResizeObserver} from "@mantine/hooks";
import React, {useEffect, useMemo, useRef, useState} from "react";
import {showError, showInfo, showSuccess} from "../../../../utilites/notifications.tsx";
import {
    addQueryStringToUrl,
    isObjectEmpty,
    removeQueryStringFromUrl,
    safeLocalStorageGet,
    safeLocalStorageRemove,
    safeLocalStorageSet
} from "../../../../utilites/helpers.ts";
import {TieredPricing} from "./Prices/Tiered";
import classNames from 'classnames';
import '../../../../styles/widget/default.scss';
import {ProductAvailabilityMessage} from "../../../common/ProductPriceAvailability";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType, Product, ProductType} from "../../../../types.ts";
import {eventsClientPublic} from "../../../../api/event.client.ts";
import {promoCodeClientPublic} from "../../../../api/promo-code.client.ts";
import {IconChevronRight, IconX} from "@tabler/icons-react"
import {getSessionIdentifier} from "../../../../utilites/sessionIdentifier.ts";
import {setCheckoutSessionIdentifier} from "../../../../utilites/checkoutSession.ts";
import {getEmbedParentUrl, getParentOrigin, sendHeightToParent} from "../../../../utilites/iframeResize.ts";
import {Constants} from "../../../../constants.ts";
import {clearWaitlistJoinedForEvent} from "../../../../hooks/useWaitlistJoined.ts";
import {OccurrenceSelector} from "../OccurrenceSelector";
import {CHECKOUT_PREFILL_PARAM_KEYS} from "../../../../hooks/useCheckoutPrefill.ts";

const AFFILIATE_EXPIRY_DAYS = 30;

const buildCheckoutPath = (
    eventId: string | undefined,
    orderShortId: string | undefined,
    sessionId?: string | null,
    extraParams: Record<string, string> = {}
) => {
    const params = new URLSearchParams();
    if (sessionId) {
        params.set('session_identifier', sessionId);
    }
    Object.entries(extraParams).forEach(([key, value]) => params.set(key, value));
    const query = params.toString();

    return `/checkout/${eventId}/${orderShortId}/details${query ? `?${query}` : ''}`;
};

const sendHeightToIframeWidgets = () => {
    const height = document.documentElement.scrollHeight;
    const widgetHeight = document.querySelector('.hi-product-widget-container')?.getBoundingClientRect().height || 0;
    sendHeightToParent(Math.max(height, widgetHeight));
};

interface SelectProductsProps {
    event: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
    backgroundType?: 'COLOR' | 'MIRROR_COVER_IMAGE';
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
    checkoutMode?: 'modal' | 'new-tab';
    showPoweredBy?: boolean;
    initialOccurrenceId?: number | null;
    onSelectedOccurrenceChange?: (occurrence?: EventOccurrence) => void;
}

const SelectProducts = (props: SelectProductsProps) => {
    const {eventId} = useParams();
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
            const data = {code: affiliateCodeFromUrl, timestamp: now};
            safeLocalStorageSet(storageKey, JSON.stringify(data));
            setAffiliateCode(affiliateCodeFromUrl);
            return;
        }

        const storedData = safeLocalStorageGet(storageKey);
        if (storedData) {
            try {
                const parsed = JSON.parse(storedData);
                const ageInDays = (now - parsed.timestamp) / (1000 * 60 * 60 * 24);
                if (ageInDays <= AFFILIATE_EXPIRY_DAYS) {
                    setAffiliateCode(parsed.code);
                } else {
                    safeLocalStorageRemove(storageKey);
                }
            } catch {
                safeLocalStorageRemove(storageKey);
            }
        }
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || !eventId) return;
        const clearWaitlist = new URLSearchParams(window.location.search).get('clear_waitlist');
        if (clearWaitlist === 'true') {
            clearWaitlistJoinedForEvent(eventId);
            removeQueryStringFromUrl('clear_waitlist');
        }
    }, [eventId]);

    useEffect(() => {
        form.setFieldValue('affiliate_code', affiliateCode || null);
    }, [affiliateCode]);

    const [selectedOccurrenceId, setSelectedOccurrenceId] = useState<number | undefined>(undefined);
    const selectedOccurrenceIdRef = useRef<number | undefined>(undefined);

    const {onSelectedOccurrenceChange} = props;
    useEffect(() => {
        onSelectedOccurrenceChange?.(
            (event?.occurrences || []).find(o => Number(o.id) === selectedOccurrenceId)
        );
    }, [selectedOccurrenceId, event?.occurrences, onSelectedOccurrenceChange]);

    const form = useForm<ProductFormPayload>({
        initialValues: {
            products: undefined,
            promo_code: props.promoCodeValid ? props.promoCode || null : null,
            affiliate_code: affiliateCode || null,
            session_identifier: undefined,
        },
    });

    const isRecurring = event?.type === EventType.RECURRING;
    const activeOccurrences = useMemo(() => {
        return (event?.occurrences || []).filter(
            occ => (occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT) && !occ.is_past
        );
    }, [event?.occurrences]);
    const needsOccurrenceSelection = isRecurring && activeOccurrences.length >= 1;
    const occurrenceSelected = !!selectedOccurrenceId;
    const eventHasEnded = useMemo(() => {
        const occurrences = event?.occurrences ?? [];
        return occurrences.length > 0 && !occurrences.some(occ => !occ.is_past);
    }, [event?.occurrences]);

    const productMutation = useMutation({
        mutationFn: (orderData: ProductFormPayload) => orderClientPublic.create(Number(eventId), orderData),

        onSuccess: (data) => queryClient.invalidateQueries()
            .then(() => {
                const sessionId = data.data.session_identifier;

                // Forward checkout-prefill params (name/email/lock) from the event page
                // to the details step, since this navigation would otherwise drop them.
                const sourceParams = new URLSearchParams(window.location.search);
                const prefillParams: Record<string, string> = {};
                CHECKOUT_PREFILL_PARAM_KEYS.forEach((key) => {
                    const value = sourceParams.get(key);
                    if (value !== null) {
                        prefillParams[key] = value;
                    }
                });

                const pathWithSession = buildCheckoutPath(eventId, data.data.short_id, sessionId, prefillParams);

                if (sessionId) {
                    setCheckoutSessionIdentifier(String(data.data.short_id), sessionId);
                }

                if (props.widgetMode === 'embedded') {
                    const parentSupportsModal = props.checkoutMode !== 'new-tab' && !!getEmbedParentUrl();

                    if (!parentSupportsModal) {
                        window.open(
                            buildCheckoutPath(eventId, data.data.short_id, sessionId, {...prefillParams, utm_source: 'embedded_widget'}),
                            '_blank',
                            'noopener,noreferrer'
                        );
                        setOrderInProcessOverlayVisible(true);
                        return;
                    }

                    window.parent.postMessage(
                        {type: 'hievents:open-checkout', path: pathWithSession},
                        getParentOrigin() || '*'
                    );
                    return;
                }

                return navigate(pathWithSession);
            }),

        onError: (error: any) => {
            const errors = error?.response?.data?.errors;
            if (errors) {
                form.setErrors(errors);
            }

            const firstError = errors
                ? Object.values(errors).flat().find((message) => typeof message === 'string')
                : undefined;

            showError(
                (firstError as string)
                || error?.response?.data?.message
                || t`Unable to create product. Please check your details`
            );
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
                promoCode,
                selectedOccurrenceId,
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

    const occurrenceEventRefetchMutation = useMutation({
        mutationFn: async (occurrenceId: number) => {
            const eventWithOccurrenceApplied = await eventsClientPublic.findByID(
                eventId,
                form.values.promo_code,
                occurrenceId,
            );
            if (selectedOccurrenceIdRef.current === occurrenceId) {
                setEvent(eventWithOccurrenceApplied.data);
            }
        },
        onError: (_error, occurrenceId) => {
            if (selectedOccurrenceIdRef.current === occurrenceId) {
                showError(t`Unable to load products for this date. Please try again.`);
            }
        },
    });

    const selectOccurrence = (occId: number) => {
        if (selectedOccurrenceIdRef.current === occId) {
            return;
        }
        selectedOccurrenceIdRef.current = occId;
        setSelectedOccurrenceId(occId);
        occurrenceEventRefetchMutation.mutate(occId);
    };

    useEffect(() => {
        let autoSelectedOccId: number | null = null;

        const selectableOccurrences = activeOccurrences;

        if (selectableOccurrences.length === 1 && selectableOccurrences[0].id) {
            autoSelectedOccId = Number(selectableOccurrences[0].id);
        }

        if (props.initialOccurrenceId) {
            const valid = selectableOccurrences.some(o => Number(o.id) === props.initialOccurrenceId);
            if (valid) {
                autoSelectedOccId = props.initialOccurrenceId;
            }
        }

        if (!props.initialOccurrenceId) {
            const occurrenceIdFromUrl = new URLSearchParams(
                typeof window !== 'undefined' ? window.location.search : ''
            ).get('occurrence_id');
            if (occurrenceIdFromUrl) {
                const occId = Number(occurrenceIdFromUrl);
                const valid = selectableOccurrences.some(o => Number(o.id) === occId);
                if (valid) {
                    autoSelectedOccId = occId;
                }
            }
        }

        if (autoSelectedOccId !== null && autoSelectedOccId !== selectedOccurrenceId) {
            if (isRecurring) {
                selectOccurrence(autoSelectedOccId);
            } else {
                selectedOccurrenceIdRef.current = autoSelectedOccId;
                setSelectedOccurrenceId(autoSelectedOccId);
            }
        }
    }, [event?.occurrences]);

    const productCategories = event?.product_categories || [];
    const productAreAvailable = productCategories && productCategories.some(category => !!category?.products?.length);
    const products: Product[] = productCategories.reduce((acc: Product[], category) => acc.concat(category.products ?? []), []);
    const waitlistAvailable = products.some(product => product.waitlist_enabled);

    const selectedProductQuantitySum = useMemo(() => {
        let total = 0;
        form.values.products?.forEach(({quantities}) => {
            quantities?.forEach(({quantity}) => {
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
        if (isRecurring && !selectedOccurrenceId) {
            showInfo(t`Please select a date and time`);
            return;
        }
        if (isRecurring && selectedOccurrenceId) {
            const selectedOcc = activeOccurrences.find(o => Number(o.id) === selectedOccurrenceId);
            if (!selectedOcc || selectedOcc.status !== EventOccurrenceStatus.ACTIVE) {
                showError(t`This date is no longer available. Please select another date.`);
                selectedOccurrenceIdRef.current = undefined;
                setSelectedOccurrenceId(undefined);
                return;
            }
        }
        if (values && selectedProductQuantitySum > 0) {
            const productsWithOccurrence = values.products?.map(product => ({
                ...product,
                event_occurrence_id: selectedOccurrenceId,
            }));
            productMutation.mutate({
                ...values,
                products: productsWithOccurrence,
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
        || products?.every(product => product.is_sold_out)
        || (needsOccurrenceSelection && !occurrenceSelected);

    const unavailableMessage = (() => {
        if (eventHasEnded) {
            return t`Ticket sales have ended for this event`;
        }
        if (isRecurring && activeOccurrences.length === 0) {
            return event?.upcoming_occurrences_sold_out
                ? t`This event is sold out`
                : t`There are no upcoming dates for this event`;
        }
        if (!productAreAvailable) {
            return t`There are no products available for this event`;
        }
        return null;
    })();

    let productIndex = 0;

    const productFormSection = (
        <>
            <div className={'hi-product-category-rows'}>
                {productCategories && productCategories.map((category) => {
                    return (
                        <div className={'hi-product-category-row'} key={category.id}>
                            <h2 className={'hi-product-category-title'} style={category.description ? {
                                marginBottom: '0px'
                            } : {}}>
                                {category.name}
                            </h2>
                            {category.description && (
                                <div className={'hi-product-category-description'}>
                                    <Spoiler maxHeight={500} showLabel={t`Show more`} hideLabel={t`Hide`}>
                                        <div dangerouslySetInnerHTML={{__html: category.description}}/>
                                    </Spoiler>
                                </div>
                            )}
                            <div className={'hi-product-rows'}>
                                {category.products?.length === 0 && (
                                    <div className={'hi-no-products'}>
                                        <p className={'hi-no-products-message'}>
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

                                    return (
                                        <div key={product.id} className={`hi-product-row ${product.is_highlighted ? 'hi-product-highlighted' : ''}`}>
                                            {product.is_highlighted && product.highlight_message && (
                                                <div className={'hi-product-highlight-message'}>
                                                    {product.highlight_message}
                                                </div>
                                            )}
                                            <div className={'hi-title-row'}>
                                                <UnstyledButton variant={'transparent'}
                                                                className={'hi-product-title'}
                                                                onClick={toggleCollapse}
                                                >
                                                    <h3>
                                                        {product.title}
                                                    </h3>
                                                    <div className={'hi-product-title-metadata'}>
                                                        {(product.is_available && !!product.quantity_available && !(isRecurring && product.product_type === ProductType.Ticket)) && (
                                                            <>
                                                                {product.quantity_available === Constants.INFINITE_TICKETS && (
                                                                    <Trans>
                                                                        Unlimited available
                                                                    </Trans>
                                                                )}
                                                                {product.quantity_available !== Constants.INFINITE_TICKETS && (
                                                                    <Trans>
                                                                        {product.quantity_available} available
                                                                    </Trans>
                                                                )}
                                                            </>
                                                        )}

                                                        {(!product.is_available && product.type === 'TIERED') && (
                                                            <ProductAvailabilityMessage product={product}
                                                                                        event={event}
                                                                                        eventOccurrenceId={selectedOccurrenceId}/>
                                                        )}

                                                        <span className={`hi-product-collapse-arrow`}>
                                                        <IconChevronRight
                                                            className={isProductCollapsed ? "" : "open"}/>
                                                        </span>
                                                    </div>
                                                </UnstyledButton>
                                            </div>
                                            <Collapse transitionDuration={100} expanded={!isProductCollapsed}
                                                      className={'hi-product-content'} hidden={isProductCollapsed}>
                                                <div className={'hi-price-tiers-rows'}>
                                                    <TieredPricing
                                                        productIndex={productIndex++}
                                                        event={event}
                                                        product={product}
                                                        form={form}
                                                        eventOccurrenceId={selectedOccurrenceId}
                                                    />
                                                </div>

                                                {product.max_per_order && form.values.products && isObjectEmpty(form.errors) && (form.values.products[currentProductIndex]?.quantities.reduce((acc, {quantity}) => acc + Number(quantity), 0) > product.max_per_order) && (
                                                    <div className={'hi-product-quantity-error'}>
                                                        <Trans>The maximum number of products
                                                            for {product.title}
                                                            is {product.max_per_order}</Trans>
                                                    </div>
                                                )}

                                                {form.errors[`products.${currentProductIndex}`] && (
                                                    <div className={'hi-product-quantity-error'}>
                                                        {form.errors[`products.${currentProductIndex}`]}
                                                    </div>
                                                )}

                                                {product.description && (
                                                    <div
                                                        className={'hi-product-description-row'}>
                                                        <Spoiler maxHeight={87} showLabel={t`Show more`}
                                                                 hideLabel={t`Hide`}>
                                                            <div dangerouslySetInnerHTML={{
                                                                __html: product.description
                                                            }}/>
                                                        </Spoiler>
                                                    </div>
                                                )}
                                            </Collapse>
                                        </div>
                                    )
                                })}
                            </div>
                        </div>
                    )
                })}
            </div>

            <div className={'hi-footer-row'}>
                {event?.settings?.product_page_message && (
                    <div dangerouslySetInnerHTML={{
                        __html: event.settings.product_page_message.replace(/\n/g, '<br/>')
                    }} className={'hi-product-page-message'}/>
                )}
                <Button disabled={isButtonDisabled} fullWidth className={'hi-continue-button'}
                        type={"submit"}
                        loading={productMutation.isPending}>
                    {props.continueButtonText || event?.settings?.continue_button_text || t`Continue`}
                </Button>
            </div>
        </>
    );

    const promoSection = (
        <div className={'hi-promo-code-row'}>
            {(!showPromoCodeInput && !form.values.promo_code) && (
                <Anchor className={'hi-have-a-promo-code-link'} underline={'always'}
                        onClick={() => setShowPromoCodeInput(true)}>
                    {t`Have a promo code?`}
                </Anchor>
            )}
            {form.values.promo_code && (
                <div className={'hi-promo-code-applied'}>
                    <span><b>{form.values.promo_code}</b> {t`applied`}</span>
                    <ActionIcon
                        type="button"
                        className={'hi-promo-code-applied-remove-icon-button'}
                        variant="transparent"
                        aria-label={t`remove`}
                        title={t`Remove`}
                        onClick={() => {
                            promoCodeEventRefetchMutation.mutate(null)
                        }}
                    >
                        <IconX stroke={1.5} size={20}/>
                    </ActionIcon>
                </div>
            )}

            {(showPromoCodeInput && !form.values.promo_code) && (
                <Group className={'hi-promo-code-input-wrapper'} wrap={'nowrap'} gap={'20px'}>
                    {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                    {/*@ts-ignore*/}
                    <TextInput autoFocus classNames={{input: 'hi-promo-code-input'}} onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            handleApplyPromoCode();
                        }
                    }} mb={0} ref={promoRef}/>
                    <Button type="button" disabled={promoCodeEventRefetchMutation.isPending}
                            className={'hi-apply-promo-code-button'} variant={'outline'}
                            onClick={handleApplyPromoCode}>
                        {t`Apply Promo Code`}
                    </Button>
                    <ActionIcon
                        type="button"
                        className={'hi-close-promo-code-input-button'}
                        variant="transparent"
                        aria-label={t`close`}
                        title={t`Close`}
                        onClick={() => setShowPromoCodeInput(false)}
                    >
                        <IconX stroke={1.5} size={20}/>
                    </ActionIcon>
                </Group>
            )}
        </div>
    );

    return (
        <div className={'hi-product-widget-container'}
             ref={resizeRef}
             style={{
                 '--widget-background-color': props.colors?.background,
                 '--widget-primary-color': props.colors?.primary,
                 '--widget-primary-text-color': props.colors?.primaryText,
                 '--widget-secondary-color': props.colors?.secondary,
                 '--widget-secondary-text-color': props.colors?.secondaryText,
                 '--widget-padding': props?.padding,
             } as React.CSSProperties}>
            {unavailableMessage && (
                <div className={classNames(['hi-no-products'])}>
                    <p className={classNames(['hi-no-products-message'])}>
                        {unavailableMessage}
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
                        <div style={{width: '100%'}}>
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
                                href={buildCheckoutPath(
                                    eventId,
                                    productMutation.data?.data.short_id,
                                    productMutation.data?.data.session_identifier,
                                    {utm_source: 'embedded_widget'}
                                )}
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
                                styles={{
                                    root: {
                                        color: props.colors?.primaryText || 'var(--primary-color, #228be6)',
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
            {(event && productAreAvailable && !eventHasEnded && !(isRecurring && activeOccurrences.length === 0)) && (
                <form target={'__blank'} onSubmit={form.onSubmit(handleProductSelection as any)}>
                    <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                    <Input type={'hidden'} {...form.getInputProps('affiliate_code')} />

                    {isRecurring ? (
                        <OccurrenceSelector
                            event={event}
                            selectedOccurrenceId={selectedOccurrenceId}
                            onSelect={(id) => selectOccurrence(Number(id))}
                            colors={props.colors}
                            isProductsLoading={occurrenceEventRefetchMutation.isPending}
                            productSlot={<>{productFormSection}{promoSection}</>}
                            waitlistAvailable={waitlistAvailable}
                        />
                    ) : (
                        productFormSection
                    )}
                </form>
            )}
            {!isRecurring && !eventHasEnded && promoSection}

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
            {(props.showPoweredBy ?? true) && (
                <PoweredByFooter style={{
                    'color': props.colors?.primaryText || '#000',
                }}/>
            )}
        </div>
    );
}

export default SelectProducts;
