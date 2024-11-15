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
import {useNavigate, useParams} from "react-router-dom";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {notifications} from "@mantine/notifications";
import {
    orderClientPublic,
    ProductFormPayload,
    ProductFormValue,
    ProductPriceQuantityFormValue
} from "../../../../api/order.client.ts";
import {useForm} from "@mantine/form";
import {range, useDisclosure, useInputState, useResizeObserver} from "@mantine/hooks";
import React, {useEffect, useMemo, useRef, useState} from "react";
import {showError, showInfo, showSuccess} from "../../../../utilites/notifications.tsx";
import {addQueryStringToUrl, isObjectEmpty, removeQueryStringFromUrl} from "../../../../utilites/helpers.ts";
import {TieredPricing} from "./Prices/Tiered";
import classNames from 'classnames';
import '../../../../styles/widget/default.scss';
import {ProductAvailabilityMessage} from "../../../common/ProductPriceAvailability";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Event, Product} from "../../../../types.ts";
import {eventsClientPublic} from "../../../../api/event.client.ts";
import {promoCodeClientPublic} from "../../../../api/promo-code.client.ts";
import {IconChevronRight, IconX} from "@tabler/icons-react"
import {getSessionIdentifier} from "../../../../utilites/sessionIdentifier.ts";
import {Constants} from "../../../../constants.ts";

const sendHeightToIframeWidgets = () => {
    const height = document.documentElement.scrollHeight;
    const urlParams = new URLSearchParams(window.location.search);
    const iframeId = urlParams.get('iframeId');

    if (!iframeId) {
        return;
    }

    window.parent.postMessage({
        type: 'resize',
        height: height,
        iframeId: iframeId
    }, '*');
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

    useEffect(() => sendHeightToIframeWidgets(), [resizeObserverRect.height]);

    const form = useForm<ProductFormPayload>({
        initialValues: {
            products: undefined,
            promo_code: props.promoCodeValid ? props.promoCode || null : null,
            session_identifier: undefined,
        },
    });

    const productMutation = useMutation({
        mutationFn: (orderData: ProductFormPayload) => orderClientPublic.create(Number(eventId), orderData),

        onSuccess: (data) => queryClient.invalidateQueries()
            .then(() => {
                const url = '/checkout/' + eventId + '/' + data.data.short_id + '/details';
                if (props.widgetMode === 'embedded') {
                    window.open(url, '_blank');
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

    let productIndex = 0;

    return (
        (<div className={'hi-product-widget-container'}
              ref={resizeRef}
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
                    <p className={classNames(['hi-no-products-message'])}>
                        {t`There are no products available for this event`}
                    </p>
                </div>
            )}
            {orderInProcessOverlayVisible && (
                <Modal withCloseButton={false} opened={true} onClose={() => setOrderInProcessOverlayVisible(false)}>
                    <div style={{textAlign: 'center', padding: '20px'}}>
                        <img style={{width: '110px'}} src={'/stopwatch-product-icon.svg'} alt={''}/>
                        <div>
                            <h4 style={{margin: '0'}}>
                                {t`Please continue in the new tab`}
                            </h4>
                            <Trans>
                                If a new tab did not open, please {' '}
                                <a href={'/checkout/' + eventId + '/' + productMutation.data?.data.short_id + '/details'}
                                   target={'_blank'} rel={'noopener noreferrer'}>
                                    <b>{t`click here`}</b>.
                                </a>
                            </Trans>
                            <Button
                                style={{marginTop: '20px'}}
                                onClick={() => setOrderInProcessOverlayVisible(false)}
                                variant={'transparent'}
                                size={'xs'}
                            >
                                {t`Dismiss`}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
            {(event && productAreAvailable) && (
                <form target={'__blank'} onSubmit={form.onSubmit(handleProductSelection as any)}>
                    <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                    <div className={'hi-product-category-rows'}>
                        {productCategories && productCategories.map((category) => {
                            return (
                                <div className={'hi-product-category-row'} key={category.id}>
                                    <h2 className={'hi-product-category-title'}>
                                        {category.name}
                                    </h2>
                                    <div className={'hi-product-rows'}>

                                        {category.products?.length === 0 && (
                                            <div className={'hi-no-products'}>
                                                <p className={'hi-no-products-message'}>
                                                    {category.no_products_message || t`There are no products available in this category`}
                                                </p>
                                            </div>
                                        )}

                                        {(category.products) && category.products.map((product) => {
                                            const quantityRange = range(product.min_per_order || 1, product.max_per_order || 25)
                                                .map((n) => n.toString());
                                            quantityRange.unshift("0");


                                            const [productIsCollapsed, {toggle: collapseProduct}] = useDisclosure(product.start_collapsed);

                                            return (
                                                <div key={product.id} className={'hi-product-row'}>
                                                    <div className={'hi-title-row'}>
                                                        <UnstyledButton variant={'transparent'}
                                                                        className={'hi-product-title'}
                                                                        onClick={collapseProduct}
                                                        >
                                                            <h3>
                                                                {product.title}
                                                            </h3>
                                                            <div className={'hi-product-title-metadata'}>
                                                                {(product.is_available && !!product.quantity_available) && (
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
                                                                    <ProductAvailabilityMessage product={product} event={event}/>
                                                                )}

                                                                <span className={`hi-product-collapse-arrow`}>
                                                                <IconChevronRight
                                                                    className={productIsCollapsed ? "" : "open"}/>
                                                                </span>
                                                            </div>
                                                        </UnstyledButton>
                                                    </div>
                                                    <Collapse transitionDuration={100} in={!productIsCollapsed} className={'hi-product-content'}>
                                                        <div className={'hi-price-tiers-rows'}>
                                                            <TieredPricing
                                                                productIndex={productIndex++}
                                                                event={event}
                                                                product={product}
                                                                form={form}
                                                            />
                                                        </div>

                                                        {product.max_per_order && form.values.products && isObjectEmpty(form.errors) && (form.values.products[productIndex]?.quantities.reduce((acc, {quantity}) => acc + Number(quantity), 0) > product.max_per_order) && (
                                                            <div className={'hi-product-quantity-error'}>
                                                                <Trans>The maximum number of products for {product.title}
                                                                    is {product.max_per_order}</Trans>
                                                            </div>
                                                        )}

                                                        {form.errors[`products.${productIndex}`] && (
                                                            <div className={'hi-product-quantity-error'}>
                                                                {form.errors[`products.${productIndex}`]}
                                                            </div>
                                                        )}

                                                        {product.description && (
                                                            <div
                                                                className={'hi-product-description-row'}>
                                                                <Spoiler maxHeight={87} showLabel={t`Show more`} hideLabel={t`Hide`}>
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
                </form>
            )}
            <div className={'hi-promo-code-row'}>
                {(!showPromoCodeInput && !form.values.promo_code) && (
                    <Anchor className={'hi-have-a-promo-code-link'}
                            onClick={() => setShowPromoCodeInput(true)}>
                        {t`Have a promo code?`}
                    </Anchor>
                )}
                {form.values.promo_code && (
                    <div className={'hi-promo-code-applied'}>
                        <span><b>{form.values.promo_code}</b> {t`applied`}</span>
                        <ActionIcon
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
            </div>
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
                    <Button disabled={promoCodeEventRefetchMutation.isPending}
                            className={'hi-apply-promo-code-button'} variant={'outline'}
                            onClick={handleApplyPromoCode}>
                        {t`Apply Promo Code`}
                    </Button>
                </Group>
            )}
            {
                /**
                 * (c) Hi.Events Ltd 2024
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
            <PoweredByFooter style={{
                'color': props.colors?.primaryText || '#000',
            }}/>
        </div>)
    );
}

export default SelectProducts;
