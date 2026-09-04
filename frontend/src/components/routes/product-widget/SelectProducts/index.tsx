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
import {useInputState, useResizeObserver} from "@mantine/hooks";
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
import {
    Event,
    EventOccurrence,
    EventOccurrenceStatus,
    EventType,
    Product,
    ProductType,
    PromoCodeDiscountAppliesTo,
    PromoCodeDiscountType,
    PromoCodeValidationResponse
} from "../../../../types.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {getDisplayPrice} from "../../../common/Currency";
import {eventsClientPublic} from "../../../../api/event.client.ts";
import {promoCodeClientPublic} from "../../../../api/promo-code.client.ts";
import {IconCheck, IconChevronDown, IconX} from "@tabler/icons-react"
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
    onCartChange?: (cart: {quantity: number; total: number}) => void;
    continueButtonRef?: React.Ref<HTMLButtonElement>;
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
    const [expandedDetails, setExpandedDetails] = useState<{ [key: number]: boolean }>({});
    const [affiliateCode, setAffiliateCode] = useState<string | null>(null);
    const [appliedPromoDetails, setAppliedPromoDetails] = useState<{
        code: string;
        response: PromoCodeValidationResponse;
    } | null>(null);

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
    const lastSelectedOccurrenceRef = useRef<EventOccurrence | undefined>(undefined);
    const [pendingInitialOccurrenceId, setPendingInitialOccurrenceId] = useState<number | undefined>(() => {
        if (props.initialOccurrenceId) {
            return props.initialOccurrenceId;
        }
        if (typeof window === 'undefined') {
            return undefined;
        }
        const occurrenceIdFromUrl = new URLSearchParams(window.location.search).get('occurrence_id');
        return occurrenceIdFromUrl ? Number(occurrenceIdFromUrl) : undefined;
    });

    const {onSelectedOccurrenceChange} = props;
    useEffect(() => {
        const lastSelected = lastSelectedOccurrenceRef.current;
        onSelectedOccurrenceChange?.(
            (event?.occurrences || []).find(o => Number(o.id) === selectedOccurrenceId)
            ?? (lastSelected && Number(lastSelected.id) === selectedOccurrenceId ? lastSelected : undefined)
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

                setAppliedPromoDetails({code: promoCode, response: validPromoCode});
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

    const selectOccurrence = (occId: number, occurrence?: EventOccurrence) => {
        if (selectedOccurrenceIdRef.current === occId) {
            return;
        }
        selectedOccurrenceIdRef.current = occId;
        lastSelectedOccurrenceRef.current = occurrence;
        setSelectedOccurrenceId(occId);
        occurrenceEventRefetchMutation.mutate(occId);
    };

    const clearSelectedOccurrence = () => {
        selectedOccurrenceIdRef.current = undefined;
        setSelectedOccurrenceId(undefined);
    };

    const initialOccurrenceAppliedRef = useRef(false);
    useEffect(() => {
        if (initialOccurrenceAppliedRef.current) {
            return;
        }
        initialOccurrenceAppliedRef.current = true;

        let autoSelectedOccId: number | null = null;

        const selectableOccurrences = activeOccurrences;

        if (selectableOccurrences.length === 1 && selectableOccurrences[0].id) {
            autoSelectedOccId = Number(selectableOccurrences[0].id);
        }

        if (pendingInitialOccurrenceId) {
            const valid = selectableOccurrences.some(o => Number(o.id) === pendingInitialOccurrenceId);
            if (valid) {
                autoSelectedOccId = pendingInitialOccurrenceId;
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

        setPendingInitialOccurrenceId(undefined);
    }, [event?.occurrences]);

    const productCategories = event?.product_categories || [];
    const productAreAvailable = productCategories && productCategories.some(category => !!category?.products?.length);
    const products: Product[] = productCategories.reduce((acc: Product[], category) => acc.concat(category.products ?? []), []);
    const topLevelProducts = products.filter(product => !product.is_addon_only);
    const waitlistAvailable = products.some(product => product.waitlist_enabled);

    const productsById = useMemo(
        () => new Map(products.map(product => [Number(product.id), product])),
        [productCategories],
    );

    const getProductFormIndex = (productId: number): number =>
        form.values.products?.findIndex(product => product.product_id === productId) ?? -1;

    const getProductQuantity = (productId: number): number => form.values.products
        ?.find(product => product.product_id === productId)
        ?.quantities?.reduce((acc, {quantity}) => acc + Number(quantity), 0) || 0;

    const getResolvableAddonIds = (product: Product): number[] =>
        (product.addon_product_ids || [])
            .map(Number)
            .filter(addonId => addonId !== Number(product.id) && productsById.has(addonId));

    const renderProductDetails = (productId: number, description: string, className: string) => {
        const isExpanded = expandedDetails[productId] ?? false;

        return (
            <div className={className}>
                <button type={'button'}
                        className={classNames('hi-details-toggle', isExpanded && 'open')}
                        aria-expanded={isExpanded}
                        onClick={() => setExpandedDetails(prev => ({...prev, [productId]: !isExpanded}))}>
                    {t`Details`}
                    <IconChevronDown size={14} stroke={2} className={isExpanded ? 'open' : ''}/>
                </button>
                <Collapse expanded={isExpanded} transitionDuration={250}>
                    <div className={'hi-product-description'}
                         dangerouslySetInnerHTML={{__html: description}}/>
                </Collapse>
            </div>
        );
    };

    useEffect(() => {
        const formProducts = form.values.products;
        if (!formProducts) {
            return;
        }

        products
            .filter(product => product.is_addon_only)
            .forEach(addon => {
                const addonId = Number(addon.id);
                const formIndex = formProducts.findIndex(formProduct => formProduct.product_id === addonId);
                if (formIndex === -1 || getProductQuantity(addonId) === 0) {
                    return;
                }

                const hasSelectedParent = topLevelProducts.some(parent =>
                    getProductQuantity(Number(parent.id)) > 0
                    && getResolvableAddonIds(parent).includes(addonId));

                if (!hasSelectedParent) {
                    form.setFieldValue(
                        `products.${formIndex}.quantities`,
                        formProducts[formIndex].quantities.map(quantity => ({...quantity, quantity: 0})),
                    );
                }
            });
    }, [form.values.products]);

    const selectedProductQuantitySum = useMemo(() => {
        let total = 0;
        form.values.products?.forEach(({quantities}) => {
            quantities?.forEach(({quantity}) => {
                total += Number(quantity);
            });
        });

        return total;
    }, [form.values.products]);

    const selectedProductsTotal = useMemo(() => {
        let total = 0;
        form.values.products?.forEach(({product_id, quantities}) => {
            const product = productsById.get(product_id);
            if (!product) {
                return;
            }
            quantities?.forEach(({quantity, price_id, price}) => {
                const selectedQuantity = Number(quantity);
                if (!selectedQuantity) {
                    return;
                }
                if (product.type === 'DONATION') {
                    total += selectedQuantity * Number(price || 0);
                    return;
                }
                const productPrice = product.prices?.find(p => Number(p.id) === price_id);
                if (productPrice) {
                    total += selectedQuantity * getDisplayPrice(productPrice, event?.settings?.price_display_mode);
                }
            });
        });

        return total;
    }, [form.values.products, productsById, event?.settings?.price_display_mode]);

    const {onCartChange} = props;
    useEffect(() => {
        onCartChange?.({quantity: selectedProductQuantitySum, total: selectedProductsTotal});
    }, [selectedProductQuantitySum, selectedProductsTotal, onCartChange]);

    useEffect(() => {
        if (form.values.promo_code) {
            const promo_code = form.values.promo_code;
            showSuccess(t`Promo ${promo_code} code applied`);
            addQueryStringToUrl('promo_code', promo_code);
        }
    }, [form.values.promo_code])

    useEffect(() => {
        const promoCode = form.values.promo_code;

        if (!promoCode) {
            setAppliedPromoDetails(null);
            return;
        }

        if (appliedPromoDetails?.code === promoCode) {
            return;
        }

        let cancelled = false;
        promoCodeClientPublic.validateCode(eventId, promoCode)
            .then((response) => {
                if (!cancelled) {
                    setAppliedPromoDetails(response.valid ? {code: promoCode, response} : null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setAppliedPromoDetails(null);
                }
            });

        return () => {
            cancelled = true;
        };
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
            const lastSelected = lastSelectedOccurrenceRef.current;
            const selectedOcc = activeOccurrences.find(o => Number(o.id) === selectedOccurrenceId)
                ?? (lastSelected && Number(lastSelected.id) === selectedOccurrenceId ? lastSelected : undefined);
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
        || topLevelProducts.every(product => product.is_sold_out)
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
        if (!productAreAvailable && !(isRecurring && activeOccurrences.length > 0)) {
            return t`There are no products available for this event`;
        }
        return null;
    })();

    const productFormSection = (
        <>
            <div className={'hi-product-category-rows'}>
                {productCategories && productCategories.map((category) => {
                    const visibleProducts = (category.products || []).filter(product => !product.is_addon_only);

                    if ((category.products?.length ?? 0) > 0 && visibleProducts.length === 0) {
                        return null;
                    }

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

                                {visibleProducts.map((product) => {
                                    const currentProductIndex = getProductFormIndex(Number(product.id));
                                    const parentQuantity = getProductQuantity(Number(product.id));
                                    const addonIds = getResolvableAddonIds(product);

                                    const isProductCollapsed = collapsedProducts[Number(product.id)] ?? product.start_collapsed;
                                    const toggleCollapse = () => {
                                        setCollapsedProducts(prev => ({
                                            ...prev,
                                            [Number(product.id)]: !isProductCollapsed
                                        }));
                                    };

                                    const isSimpleProduct = product.type !== 'TIERED'
                                        && product.type !== 'DONATION'
                                        && (product.prices?.length ?? 0) === 1;

                                    const availabilityState = product.is_sold_out
                                        ? 'sold-out'
                                        : product.is_before_sale_start_date
                                            ? 'upcoming'
                                            : product.is_after_sale_end_date
                                                ? 'ended'
                                                : undefined;

                                    const collapsedFromPrice = (() => {
                                        if (!isProductCollapsed || product.type !== 'TIERED') {
                                            return null;
                                        }
                                        const availablePrices = (product.prices || []).filter(price => price.is_available);
                                        if (availablePrices.length === 0) {
                                            return null;
                                        }
                                        return Math.min(...availablePrices.map(price =>
                                            getDisplayPrice(price, event?.settings?.price_display_mode)));
                                    })();

                                    return (
                                        <div key={product.id}
                                             className={`hi-product-row ${product.is_highlighted ? 'hi-product-highlighted' : ''}`}
                                             data-availability={availabilityState}>
                                            {product.is_highlighted && product.highlight_message && (
                                                <div className={'hi-product-highlight-message'}>
                                                    {product.highlight_message}
                                                </div>
                                            )}
                                            <div className={'hi-title-row'}>
                                                <UnstyledButton className={'hi-product-title'}
                                                                onClick={toggleCollapse}
                                                >
                                                    <h3>
                                                        {product.title}
                                                    </h3>
                                                    <div className={'hi-product-title-metadata'}>
                                                        {(product.is_available && !!product.quantity_available && !(isRecurring && product.product_type === ProductType.Ticket)) && (
                                                            <>
                                                                {product.quantity_available === Constants.INFINITE_TICKETS && (
                                                                    <span className={'hi-quantity-remaining-note'}>
                                                                        <Trans>
                                                                            Unlimited available
                                                                        </Trans>
                                                                    </span>
                                                                )}
                                                                {product.quantity_available !== Constants.INFINITE_TICKETS && (
                                                                    <span className={'hi-scarcity-pill'}>
                                                                        <Trans>
                                                                            {product.quantity_available} available
                                                                        </Trans>
                                                                    </span>
                                                                )}
                                                            </>
                                                        )}

                                                        {(!product.is_available && product.type === 'TIERED') && (
                                                            <span className={'hi-product-availability'}
                                                                  data-reason={availabilityState}>
                                                                <ProductAvailabilityMessage product={product}
                                                                                            event={event}
                                                                                            eventOccurrenceId={selectedOccurrenceId}/>
                                                            </span>
                                                        )}

                                                        <span className={`hi-product-collapse-arrow`}>
                                                        <IconChevronDown
                                                            className={isProductCollapsed ? "" : "open"}/>
                                                        </span>
                                                    </div>
                                                </UnstyledButton>
                                            </div>
                                            {isSimpleProduct && (
                                                <div className={'hi-product-header-body'}>
                                                    <TieredPricing
                                                        productIndex={currentProductIndex}
                                                        event={event}
                                                        product={product}
                                                        form={form}
                                                        eventOccurrenceId={selectedOccurrenceId}
                                                        displayMode={'header'}
                                                        showStepper={!isProductCollapsed}
                                                    />
                                                </div>
                                            )}
                                            {collapsedFromPrice !== null && (
                                                <div className={'hi-price-from-summary'}>
                                                    {t`From ${formatCurrency(collapsedFromPrice, event?.currency)}`}
                                                </div>
                                            )}
                                            <Collapse transitionDuration={100} expanded={!isProductCollapsed}
                                                      className={'hi-product-content'} hidden={isProductCollapsed}>
                                                {!isSimpleProduct && (
                                                    <div className={'hi-price-tiers-rows'}>
                                                        <TieredPricing
                                                            productIndex={currentProductIndex}
                                                            event={event}
                                                            product={product}
                                                            form={form}
                                                            eventOccurrenceId={selectedOccurrenceId}
                                                        />
                                                    </div>
                                                )}

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

                                                {product.description && renderProductDetails(
                                                    Number(product.id),
                                                    product.description,
                                                    'hi-product-description-row',
                                                )}

                                                {addonIds.length > 0 && (
                                                    <div className={'hi-product-addons'}
                                                         data-inactive={parentQuantity === 0 || undefined}>
                                                        <div className={'hi-product-addons-heading'}>
                                                            <Trans>Add-ons</Trans>
                                                            {parentQuantity === 0 && (
                                                                <span className={'hi-product-addons-note'}>
                                                                    <Trans>Add {product.title} first</Trans>
                                                                </span>
                                                            )}
                                                        </div>
                                                        {addonIds.map((addonId) => {
                                                            const addon = productsById.get(addonId);
                                                            if (!addon) {
                                                                return null;
                                                            }
                                                            const addonFormIndex = getProductFormIndex(addonId);

                                                            return (
                                                                <div key={addonId}
                                                                     className={classNames('hi-product-addon', addon.is_highlighted && 'hi-product-addon-highlighted')}>
                                                                    {addon.is_highlighted && addon.highlight_message && (
                                                                        <div className={'hi-product-addon-highlight-message'}>
                                                                            {addon.highlight_message}
                                                                        </div>
                                                                    )}
                                                                    <div className={'hi-product-addon-title'}>
                                                                        {addon.title}
                                                                    </div>
                                                                    <TieredPricing
                                                                        productIndex={addonFormIndex}
                                                                        event={event}
                                                                        product={addon}
                                                                        form={form}
                                                                        eventOccurrenceId={selectedOccurrenceId}
                                                                    />
                                                                    {form.errors[`products.${addonFormIndex}`] && (
                                                                        <div className={'hi-product-quantity-error'}>
                                                                            {form.errors[`products.${addonFormIndex}`]}
                                                                        </div>
                                                                    )}
                                                                    {addon.description && renderProductDetails(
                                                                        addonId,
                                                                        addon.description,
                                                                        'hi-product-addon-description',
                                                                    )}
                                                                </div>
                                                            );
                                                        })}
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
                        ref={props.continueButtonRef}
                        type={"submit"}
                        data-testid="checkout-continue-button"
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
                    <IconCheck size={16} stroke={2.5} className={'hi-promo-code-applied-check'}/>
                    <span>
                        <b>{form.values.promo_code}</b>{' '}
                        {(appliedPromoDetails?.response.discount_type === PromoCodeDiscountType.Fixed
                            && appliedPromoDetails?.response.discount_applies_to === PromoCodeDiscountAppliesTo.Order
                            && appliedPromoDetails?.response.applies_to_all_products
                            && appliedPromoDetails?.response.discount)
                            ? t`applied — ${formatCurrency(appliedPromoDetails.response.discount, event?.currency)} off your order`
                            : t`applied`}
                    </span>
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
                <Group className={'hi-promo-code-input-wrapper'} wrap={'nowrap'} gap={'10px'}>
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
                            data-testid="promo-code-apply-button"
                            onClick={handleApplyPromoCode}>
                        {t`Apply`}
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

    const noProductsForOccurrence = (
        <div className={'hi-no-products'}>
            <p className={'hi-no-products-message'}>
                {t`There are no products available for this date. Please choose another date.`}
            </p>
            <Button type={'button'} variant={'outline'} onClick={clearSelectedOccurrence}>
                {t`Choose another date`}
            </Button>
        </div>
    );

    const showRecurringSelector = isRecurring && activeOccurrences.length > 0;

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
            {(event && !eventHasEnded && (showRecurringSelector || (!isRecurring && productAreAvailable))) && (
                <form target={'__blank'} onSubmit={form.onSubmit(handleProductSelection as any)}>
                    <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                    <Input type={'hidden'} {...form.getInputProps('affiliate_code')} />

                    {isRecurring ? (
                        <OccurrenceSelector
                            event={event}
                            selectedOccurrenceId={selectedOccurrenceId}
                            pendingInitialOccurrenceId={pendingInitialOccurrenceId}
                            onSelect={(id, occurrence) => selectOccurrence(Number(id), occurrence)}
                            colors={props.colors}
                            isProductsLoading={occurrenceEventRefetchMutation.isPending}
                            productSlot={productAreAvailable
                                ? <>{productFormSection}{promoSection}</>
                                : noProductsForOccurrence}
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
