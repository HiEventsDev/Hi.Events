import {useMutation} from "@tanstack/react-query";
import {FinaliseOrderPayload, orderClientPublic} from "../../../../api/order.client.ts";
import {useNavigate, useParams} from "react-router";
import {
    Button,
    Checkbox,
    NativeSelect,
    SegmentedControl,
    Skeleton,
    Text,
    TextInput,
    Tooltip
} from "@mantine/core";
import {IconArrowRight, IconCheck, IconCircleCheck} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {notifications} from "@mantine/notifications";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useGetEventQuestionsPublic} from "../../../../queries/useGetEventQuestionsPublic.ts";
import {CheckoutOrderQuestions, CheckoutProductQuestions} from "../../../common/CheckoutQuestion";
import {Event, IdParam, Question} from "../../../../types.ts";
import {useEffect, useState} from "react";
import {InputGroup} from "../../../common/InputGroup";
import {Card} from "../../../common/Card";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {getConfig} from "../../../../utilites/config.ts";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {InlineOrderSummary} from "../../../common/InlineOrderSummary";
import {eventCheckoutPath, eventHomepagePath} from "../../../../utilites/urlHelper.ts";
import {showInfo} from "../../../../utilites/notifications.tsx";
import countries from "../../../../../data/countries.json";
import classes from "./CollectInformation.module.scss";
import {trackEvent, AnalyticsEvents} from "../../../../utilites/analytics.ts";

const LoadingSkeleton = () =>
    (
        <CheckoutContent>
            <Skeleton mb={20} height={200}/>
            <Skeleton mb={20} height={200}/>
            <Skeleton mb={20} height={200}/>
        </CheckoutContent>
    );

export const CollectInformation = () => {
    const {eventId, orderShortId} = useParams();
    const navigate = useNavigate();
    const {
        isFetched: isOrderFetched,
        data: order,
        data: {order_items: orderItems} = {},
        isError: isOrderError,
        error: orderError,
    } = useGetOrderPublic(eventId, orderShortId, ['event']);
    const {
        data: event,
        data: {product_categories: productCategories} = {},
        isFetched: isEventFetched,
        isError: isEventError,
    } = useGetEventPublic(eventId, isOrderFetched, !!order?.promo_code, order?.promo_code ?? null);
    const {
        data: questions,
        isFetched: isQuestionsFetched,
        isError: isQuestionsError
    } = useGetEventQuestionsPublic(eventId);
    const productQuestions = questions?.filter(question => question.belongs_to === "PRODUCT");
    const orderQuestions = questions?.filter(question => question.belongs_to === "ORDER");
    const products = productCategories?.flatMap(category => category.products);
    const requireBillingAddress = event?.settings?.require_billing_address;
    const isPerOrderCollection = event?.settings?.attendee_details_collection_method === 'PER_ORDER';
    const [copyOption, setCopyOption] = useState<'none' | 'first' | 'all'>('none');

    const isEmailValid = (email: string) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    const EmailCheckIcon = () => (
        <IconCircleCheck size={18} style={{color: 'var(--primary-color, #10B981)'}}/>
    );

    let productIndex = 0;

    const form = useForm({
        initialValues: {
            order: {
                first_name: "",
                last_name: "",
                email: "",
                email_confirmation: "",
                address: {},
                questions: {},
                opted_into_marketing: false,
            },
            products: [{
                first_name: "",
                last_name: "",
                email: "",
                email_confirmation: "",
                product_price_id: "",
                product_id: "",
                questions: {},
            }],
        },
        validate: {
            order: {
                email_confirmation: (value, values) =>
                    value !== values.order.email ? t`Email addresses do not match` : null,
            },
            products: {
                email_confirmation: (value, values, path) => {
                    const index = parseInt(path.split('.')[1]);
                    const product = values.products[index];
                    if (product && product.email !== value) {
                        return t`Email addresses do not match`;
                    }
                    return null;
                },
            },
        },
        validateInputOnBlur: true,
    });

    const getTicketAttendeeIndices = (): number[] => {
        if (!products || !form.values.products) return [];

        const attendeeProductIds = new Set<IdParam>(
            products
                .filter(product => product && product.product_type === 'TICKET')
                .map(product => product.id)
        );

        return form.values.products
            .map((product, index) => attendeeProductIds.has(product.product_id) ? index : -1)
            .filter(index => index !== -1);
    };

    const getFirstTicketAttendeeIndex = (): number => {
        const indices = getTicketAttendeeIndices();
        return indices.length > 0 ? indices[0] : -1;
    };

    const totalTicketAttendees = getTicketAttendeeIndices().length;

    const areOrderDetailsComplete = () => {
        const {first_name, last_name, email} = form.values.order;
        return first_name.trim() !== '' && last_name.trim() !== '' && isEmailValid(email);
    };

    const copyDetailsToAttendees = (option: 'none' | 'first' | 'all') => {
        if (!products) return;

        const ticketIndices = getTicketAttendeeIndices();
        if (ticketIndices.length === 0) return;

        const updatedProducts = form.values.products.map((product, index) => {
            const isTicketAttendee = ticketIndices.includes(index);
            const isFirst = index === ticketIndices[0];
            const shouldCopy = option === 'all' ? isTicketAttendee : (option === 'first' && isFirst);

            if (isTicketAttendee) {
                if (shouldCopy) {
                    return {
                        ...product,
                        first_name: form.values.order.first_name,
                        last_name: form.values.order.last_name,
                        email: form.values.order.email,
                        email_confirmation: form.values.order.email,
                    };
                } else {
                    return {
                        ...product,
                        first_name: "",
                        last_name: "",
                        email: "",
                        email_confirmation: "",
                    };
                }
            }
            return product;
        });

        form.setValues({
            ...form.values,
            products: updatedProducts,
        });
    };

    const handleCopyOptionChange = (value: string) => {
        const option = value as 'none' | 'first' | 'all';

        // Only allow copying if order details are complete
        if (option !== 'none' && !areOrderDetailsComplete()) {
            return;
        }

        setCopyOption(option);
        copyDetailsToAttendees(option);
    };

    // Reset copy option if order details become incomplete
    useEffect(() => {
        if (copyOption !== 'none' && !areOrderDetailsComplete()) {
            setCopyOption('none');
            copyDetailsToAttendees('none');
        }
    }, [form.values.order.first_name, form.values.order.last_name, form.values.order.email]);

    const mutation = useMutation({
        mutationFn: (orderData: FinaliseOrderPayload) => orderClientPublic.finaliseOrder(Number(eventId), String(orderShortId), orderData),

        onSuccess: (data) => {
            const nextPage = order?.is_payment_required ? 'payment' : 'summary';
            if (nextPage === 'summary') {
                trackEvent(AnalyticsEvents.PURCHASE_COMPLETED_FREE);
            }
            navigate(eventCheckoutPath(eventId, data.data.short_id, nextPage));
        },

        onError: (error: any) => {
            if (error?.response?.data?.errors && Object.keys(error?.response?.data?.errors).length > 0) {
                form.setErrors(error.response.data.errors);
            } else if (error?.response?.data?.message) {
                notifications.show({
                    message: error?.response?.data?.message,
                });

                // if it's a 409, we need to redirect to the event page as the order is no longer valid
                if (error.response.status === 409) {
                    navigate(eventHomepagePath(event as Event));
                }
            }
        }
    });

    const createProductIdToQuestionMap = () => {
        const productIdToQuestionMap = new Map();

        productQuestions?.forEach(question => {
            question.product_ids?.forEach(id => {
                const existingQuestions = productIdToQuestionMap.get(id);
                productIdToQuestionMap.set(
                    id,
                    existingQuestions ? [...existingQuestions, question] : [question]
                );
            });
        });

        return productIdToQuestionMap;
    }

    const createProductsAndQuestions = (productIdToQuestionMap: Map<number, Question[]>) => {
        const products: any = [];

        orderItems?.forEach(orderItem => {
            Array.from(Array(orderItem?.quantity)).map(() => {
                products.push({
                    product_price_id: orderItem?.product_price_id,
                    product_id: orderItem?.product_id,
                    first_name: "",
                    last_name: "",
                    email: "",
                    email_confirmation: "",
                    questions: productIdToQuestionMap.get(orderItem?.product_id)?.map((question: Question) => {
                        return {
                            question_id: question.id,
                            response: {},
                        }
                    })
                });
            });
        });

        return products;
    }

    const createFormOrderQuestions = () => {
        const formOrderQuestions: any = [];

        orderQuestions?.forEach(orderQuestion => {
            formOrderQuestions.push({
                question_id: orderQuestion.id,
                response: {},
            });
        });

        return formOrderQuestions;
    }

    const handleSubmit = (values: any) => {
        mutation.mutate(values);
    };

    useEffect(() => {
        if (isEventFetched && isOrderFetched && isQuestionsFetched && productQuestions && orderQuestions) {
            const products = createProductsAndQuestions(createProductIdToQuestionMap());
            const formOrderQuestions = createFormOrderQuestions();

            form.setValues({
                ...form.values,
                products: products,
                order: {
                    ...form.values.order,
                    questions: formOrderQuestions,
                },
            });
        }
    }, [isEventFetched, isOrderFetched, isQuestionsFetched]);

    useEffect(() => {
        if ((order && event) && order?.is_expired) {
            showInfo(t`This order has expired. Please start again.`);
            navigate(`/event/${eventId}/${event.slug}`);
        }
    }, [order, event]);

    if (!isEventFetched || !isOrderFetched) {
        return <LoadingSkeleton/>
    }

    if (order?.status === 'ABANDONED') {
        return <HomepageInfoMessage
            status="cancelled"
            message={t`Order was cancelled`}
            subtitle={t`This order was abandoned. You can start a new order anytime.`}
            link={eventHomepagePath(event as Event)}
            linkText={t`Back to Event`}
        />;
    }

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        return <HomepageInfoMessage
            status="awaiting_payment"
            message={t`Waiting for payment`}
            subtitle={t`Complete your payment to secure your tickets.`}
            link={eventCheckoutPath(eventId, orderShortId, 'payment')}
            linkText={t`Complete Payment`}
        />;
    }

    if (order?.status === 'COMPLETED') {
        return <HomepageInfoMessage
            status="success"
            message={t`Order complete`}
            subtitle={t`Your tickets have been confirmed.`}
            link={eventCheckoutPath(eventId, orderShortId, 'summary')}
            linkText={t`View Order Details`}
        />;
    }

    if (order?.status === 'CANCELLED') {
        return <HomepageInfoMessage
            status="cancelled"
            message={t`Order cancelled`}
            subtitle={t`This order was cancelled. You can start a new order anytime.`}
            link={eventHomepagePath(event as Event)}
            linkText={t`Back to Event`}
        />;
    }

    if (isOrderError && orderError?.response?.status === 404) {
        return (
            <HomepageInfoMessage
                status="not_found"
                message={t`Order not found`}
                subtitle={t`We couldn't find this order. It may have been removed.`}
                link={eventHomepagePath(event as Event)}
                linkText={t`Go to Event Page`}
            />
        );
    }

    if (isOrderError || isEventError || isQuestionsError) {
        return (
            <HomepageInfoMessage
                status="error"
                message={t`Something went wrong`}
                subtitle={t`We hit a snag loading this page. Please try again.`}
                link={eventHomepagePath(event as Event)}
                linkText={t`Back to Event`}
            />
        );
    }

    const orderRequiresAttendeeDetails = orderItems?.some(orderItem => {
        const product = products?.find(product => product!.id === orderItem.product_id);
        return product?.product_type === 'TICKET';
    });

    return (
        <form onSubmit={form.onSubmit(handleSubmit)}>
            <CheckoutContent>
                {(event && order) && (
                    <InlineOrderSummary event={event} order={order} defaultExpanded={true}/>
                )}

                <h2 className={classes.sectionHeading}>
                    {t`Your Details`}
                </h2>
                <p className={classes.sectionHelper}>
                    {t`We'll send your tickets to this email`}
                </p>

                <Card>
                    <InputGroup>
                        <TextInput
                            withAsterisk
                            label={t`First Name`}
                            placeholder={t`First name`}
                            {...form.getInputProps("order.first_name")}
                        />
                        <TextInput
                            withAsterisk
                            label={t`Last Name`}
                            placeholder={t`Last Name`}
                            {...form.getInputProps("order.last_name")}
                        />
                    </InputGroup>

                    <InputGroup>
                        <TextInput
                            withAsterisk
                            type={"email"}
                            label={t`Email Address`}
                            placeholder={t`Email Address`}
                            rightSection={isEmailValid(form.values.order.email) ? <EmailCheckIcon/> : null}
                            {...form.getInputProps("order.email")}
                        />
                        <TextInput
                            withAsterisk
                            type={"email"}
                            label={t`Confirm Email Address`}
                            placeholder={t`Confirm Email Address`}
                            rightSection={isEmailValid(form.values.order.email_confirmation) ? <EmailCheckIcon/> : null}
                            {...form.getInputProps("order.email_confirmation")}
                        />
                    </InputGroup>

                    {orderRequiresAttendeeDetails && !isPerOrderCollection && totalTicketAttendees > 0 && (
                        <div className={classes.copyDetailsSection}>
                            {totalTicketAttendees === 1 ? (
                                <Tooltip
                                    label={t`Fill in your details above first`}
                                    disabled={areOrderDetailsComplete()}
                                    position="right"
                                    withArrow
                                >
                                    <div style={{display: 'inline-block'}}>
                                        <Checkbox
                                            size="sm"
                                            label={t`Copy details to first attendee`}
                                            checked={copyOption === 'first'}
                                            disabled={!areOrderDetailsComplete()}
                                            onChange={(e) => handleCopyOptionChange(e.currentTarget.checked ? 'first' : 'none')}
                                        />
                                    </div>
                                </Tooltip>
                            ) : (
                                <div className={classes.copyDetailsMultiple}>
                                    <Text size="sm" c="dimmed"
                                          className={classes.copyLabel}>{t`Copy my details to:`}</Text>
                                    <Tooltip
                                        label={t`Fill in your details above first`}
                                        disabled={areOrderDetailsComplete()}
                                        withArrow
                                    >
                                        <SegmentedControl
                                            size="xs"
                                            value={copyOption}
                                            onChange={handleCopyOptionChange}
                                            disabled={!areOrderDetailsComplete()}
                                            data={[
                                                {label: t`None`, value: 'none'},
                                                {label: t`First attendee`, value: 'first'},
                                                {label: t`All attendees`, value: 'all'},
                                            ]}
                                        />
                                    </Tooltip>
                                </div>
                            )}
                        </div>
                    )}

                    {requireBillingAddress && (
                        <>
                            <h3 style={{marginBottom: 5}}>
                                {t`Billing Address`}
                            </h3>

                            <InputGroup>
                                <TextInput
                                    withAsterisk
                                    label={t`Address Line 1`}
                                    placeholder={t`Address Line 1`}
                                    {...form.getInputProps("order.address.address_line_1")}
                                />
                                <TextInput
                                    label={t`Address Line 2`}
                                    placeholder={t`Address Line 2`}
                                    {...form.getInputProps("order.address.address_line_2")}
                                />
                            </InputGroup>

                            <InputGroup>
                                <TextInput
                                    withAsterisk
                                    label={t`City`}
                                    placeholder={t`City`}
                                    {...form.getInputProps("order.address.city")}
                                />
                                <TextInput
                                    withAsterisk
                                    label={t`State or Region`}
                                    placeholder={t`State or Region`}
                                    {...form.getInputProps("order.address.state_or_region")}
                                />
                            </InputGroup>

                            <InputGroup>
                                {/* Postal Code and Country */}
                                <TextInput
                                    label={t`ZIP / Postal Code`}
                                    placeholder={t`ZIP or Postal Code`}
                                    {...form.getInputProps("order.address.zip_or_postal_code")}
                                />
                                <NativeSelect
                                    withAsterisk
                                    label={t`Country`}
                                    data={countries}
                                    {...form.getInputProps("order.address.country")}
                                />
                            </InputGroup>
                        </>
                    )}

                    {orderQuestions && <CheckoutOrderQuestions form={form} questions={orderQuestions}/>}

                    {event?.settings?.show_marketing_opt_in && (
                        <Checkbox
                            mt="md"
                            label={t`Keep me updated on news and events from ${event?.organizer?.name || t`this organizer`}`}
                            {...form.getInputProps('order.opted_into_marketing', {type: 'checkbox'})}
                        />
                    )}
                </Card>

                {orderItems?.map(orderItem => {
                    const product = products?.find(product => product!.id === orderItem.product_id);
                    const productRequiresDetails = product?.product_type === 'TICKET' && !isPerOrderCollection;
                    const productHasQuestions = productQuestions?.some(question => question.product_ids?.includes(orderItem.product_id));

                    if (!product) {
                        return null;
                    }

                    if (!productRequiresDetails && !productHasQuestions) {
                        // Still increment productIndex for each item in the quantity
                        // to maintain correct form field indices
                        productIndex += orderItem.quantity ?? 0;
                        return null;
                    }

                    return (
                        <div key={orderItem.product_id + orderItem.id} className={classes.ticketSection}>
                            <div className={classes.ticketTypeHeader}>
                                <h3>{orderItem?.item_name}</h3>
                                <span className={classes.ticketCountBadge}>
                                    {orderItem.quantity === 1
                                        ? t`1 ticket`
                                        : t`${orderItem.quantity} tickets`}
                                </span>
                            </div>
                            {Array.from(Array(orderItem?.quantity)).map((_, index) => {
                                const currentProductIndex = productIndex;
                                const ticketIndices = getTicketAttendeeIndices();
                                const isTicketAttendee = ticketIndices.includes(currentProductIndex);
                                const isFirstTicketAttendee = currentProductIndex === getFirstTicketAttendeeIndex();
                                const isCopied = isTicketAttendee && (
                                    copyOption === 'all' || (copyOption === 'first' && isFirstTicketAttendee)
                                );

                                // Check if current values still match the order details
                                const currentProduct = form.values.products[currentProductIndex];
                                const valuesMatchOrder = currentProduct &&
                                    currentProduct.first_name === form.values.order.first_name &&
                                    currentProduct.last_name === form.values.order.last_name &&
                                    currentProduct.email === form.values.order.email;

                                // Only show badge if copied AND values still match
                                const showCopiedBadge = isCopied && productRequiresDetails && valuesMatchOrder;

                                const productInputs = (
                                    <Card key={`${orderItem.id} ${index}`} className={classes.attendeeCard}>
                                        <div className={classes.attendeeCardHeader}>
                                            <div className={classes.attendeeHeaderLeft}>
                                                <div className={classes.attendeeNumber}>
                                                    {index + 1}
                                                </div>
                                                <div className={classes.attendeeInfo}>
                                                    <h4>
                                                        {product.product_type === 'TICKET' ? t`Attendee` : t`Item`} {index + 1}
                                                    </h4>
                                                    <span className={classes.attendeeTicketType}>
                                                        {orderItem?.item_name}
                                                    </span>
                                                </div>
                                            </div>
                                            {showCopiedBadge && (
                                                <span className={classes.copiedBadge}>
                                                    {t`Copied from above`}
                                                </span>
                                            )}
                                        </div>

                                        {productRequiresDetails && (
                                            <>
                                                <InputGroup>
                                                    <TextInput
                                                        withAsterisk
                                                        label={t`First Name`}
                                                        placeholder={t`First name`}
                                                        {...form.getInputProps(`products.${currentProductIndex}.first_name`)}
                                                    />
                                                    <TextInput
                                                        withAsterisk
                                                        label={t`Last Name`}
                                                        placeholder={t`Last Name`}
                                                        {...form.getInputProps(`products.${currentProductIndex}.last_name`)}
                                                    />
                                                </InputGroup>

                                                <InputGroup>
                                                    <TextInput
                                                        withAsterisk
                                                        type={"email"}
                                                        label={t`Email Address`}
                                                        placeholder={t`Email Address`}
                                                        rightSection={isEmailValid(form.values.products[currentProductIndex]?.email || '') ?
                                                            <EmailCheckIcon/> : null}
                                                        {...form.getInputProps(`products.${currentProductIndex}.email`)}
                                                    />
                                                    <TextInput
                                                        withAsterisk
                                                        type={"email"}
                                                        label={t`Confirm Email Address`}
                                                        placeholder={t`Confirm Email Address`}
                                                        rightSection={isEmailValid(form.values.products[currentProductIndex]?.email_confirmation || '') ?
                                                            <EmailCheckIcon/> : null}
                                                        {...form.getInputProps(`products.${currentProductIndex}.email_confirmation`)}
                                                    />
                                                </InputGroup>
                                            </>
                                        )}

                                        {productQuestions &&
                                            <CheckoutProductQuestions
                                                index={currentProductIndex}
                                                product={product}
                                                form={form}
                                                questions={productQuestions}/>}
                                    </Card>
                                );

                                productIndex++;

                                return productInputs;
                            })}
                        </div>
                    );
                })}

                {!!event?.settings?.pre_checkout_message && (
                    <Card>
                        <div dangerouslySetInnerHTML={{__html: event?.settings?.pre_checkout_message}}/>
                    </Card>
                )}

                <div className={classes.checkoutActions}>
                    <Button
                        className={classes.continueButton}
                        loading={mutation.isPending}
                        type="submit"
                        rightSection={order?.is_payment_required ? <IconArrowRight size={18}/> : undefined}
                        leftSection={!order?.is_payment_required ? <IconCheck size={18}/> : undefined}
                    >
                        {order?.is_payment_required ? t`Continue to Payment` : t`Complete Order`}
                    </Button>
                    {!!getConfig('VITE_TOS_URL') && (
                        <p className={classes.tosNotice}>
                            <Trans>
                                By continuing, you agree to the{' '}
                                <a
                                    href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service') as string}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {getConfig('VITE_APP_NAME', 'Hi.Events')} Terms of Service
                                </a>
                            </Trans>
                        </p>
                    )}
                </div>

            </CheckoutContent>
        </form>
    );
}

export default CollectInformation;
