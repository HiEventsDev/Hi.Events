import {useMutation} from "@tanstack/react-query";
import {FinaliseOrderPayload, orderClientPublic} from "../../../../api/order.client.ts";
import {useNavigate, useParams} from "react-router";
import {Button, Group, NativeSelect, Skeleton, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {notifications} from "@mantine/notifications";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {useGetEventQuestionsPublic} from "../../../../queries/useGetEventQuestionsPublic.ts";
import {CheckoutOrderQuestions, CheckoutProductQuestions} from "../../../common/CheckoutQuestion";
import {Event, IdParam, Order, Question} from "../../../../types.ts";
import {useEffect} from "react";
import {t} from "@lingui/macro";
import {InputGroup} from "../../../common/InputGroup";
import {Card} from "../../../common/Card";
import {IconCopy} from "@tabler/icons-react";
import {CheckoutFooter} from "../../../layouts/Checkout/CheckoutFooter";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {eventCheckoutPath, eventHomepagePath} from "../../../../utilites/urlHelper.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {showInfo} from "../../../../utilites/notifications.tsx";
import countries from "../../../../../data/countries.json";

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

    let productIndex = 0;

    const form = useForm({
        initialValues: {
            order: {
                first_name: "",
                last_name: "",
                email: "",
                address: {},
                questions: {},
            },
            products: [{
                first_name: "",
                last_name: "",
                email: "",
                product_price_id: "",
                product_id: "",
                questions: {},
            }],
        },
    });

    const copyDetailsToAllAttendees = () => {
        if (!products) {
            return;
        }

        const attendeeProductIds = new Set<IdParam>(
            products
                .filter(product => product && product.product_type === 'TICKET')
                .map(product => product.id)
        );

        const updatedProducts = form.values.products.map(product => {
            if (attendeeProductIds.has(product.product_id)) {
                return {
                    ...product,
                    first_name: form.values.order.first_name,
                    last_name: form.values.order.last_name,
                    email: form.values.order.email,
                };
            }
            return product;
        });

        form.setValues({
            ...form.values,
            products: updatedProducts,
        });
    };

    const mutation = useMutation({
        mutationFn: (orderData: FinaliseOrderPayload) => orderClientPublic.finaliseOrder(Number(eventId), String(orderShortId), orderData),

        onSuccess: (data) => {
            const nextPage = order?.is_payment_required ? 'payment' : 'summary';
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

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        return <HomepageInfoMessage
            message={t`This order is awaiting payment`}
            link={eventCheckoutPath(eventId, orderShortId, 'payment')}
            linkText={t`Complete payment`}
        />;
    }

    if (order?.status === 'COMPLETED') {
        return <HomepageInfoMessage
            message={t`This order is complete`}
            link={eventCheckoutPath(eventId, orderShortId, 'summary')}
            linkText={t`View order details`}
        />;
    }

    if (order?.status === 'CANCELLED') {
        return <HomepageInfoMessage
            message={t`This order has been cancelled`}
            link={eventHomepagePath(event as Event)}
            linkText={t`Go to event homepage`}
        />;
    }

    if (isOrderError && orderError?.response?.status === 404) {
        return (
            <>
                <HomepageInfoMessage
                    message={t`Sorry, this order no longer exists.`}
                    link={eventHomepagePath(event as Event)}
                    linkText={t`Back to event page`}
                />
            </>
        );
    }

    if (isOrderError || isEventError || isQuestionsError) {
        return (
            <>
                <HomepageInfoMessage
                    message={t`Sorry, something went wrong loading this page.`}
                    link={eventHomepagePath(event as Event)}
                    linkText={t`Back to event page`}
                />
            </>
        );
    }

    const orderRequiresAttendeeDetails = orderItems?.some(orderItem => {
        const product = products?.find(product => product!.id === orderItem.product_id);
        return product?.product_type === 'TICKET';
    });

    return (
        <form onSubmit={form.onSubmit(handleSubmit)}>
            <CheckoutContent>
                <h2>
                    {t`Your Details`}
                </h2>

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

                    <TextInput
                        withAsterisk
                        type={"email"}
                        label={t`Email Address`}
                        placeholder={t`Email Address`}
                        {...form.getInputProps("order.email")}
                    />

                    {orderRequiresAttendeeDetails && (
                        <Button p={0} ml={0} size={'sm'} variant={'transparent'} leftSection={<IconCopy size={14}/>}
                                onClick={copyDetailsToAllAttendees}>
                            {t`Copy details to all attendees`}
                        </Button>
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
                </Card>

                {orderItems?.map(orderItem => {
                    const product = products?.find(product => product!.id === orderItem.product_id);
                    const productRequiresDetails = product?.product_type === 'TICKET';
                    const productHasQuestions = productQuestions?.some(question => question.product_ids?.includes(orderItem.product_id));

                    if (!product) {
                        return;
                    }

                    if (!productRequiresDetails && !productHasQuestions) {
                        return;
                    }

                    return (
                        <div key={orderItem.product_id + orderItem.id}>
                            <h3>{orderItem?.item_name}</h3>
                            {Array.from(Array(orderItem?.quantity)).map((_, index) => {
                                const productInputs = (
                                    <>
                                        <Card key={`${orderItem.id} ${index}`}>
                                            <h4 style={{marginTop: 0}}>
                                                {product.product_type === 'TICKET' ? t`Attendee` : t`Item`} {index + 1} {t`Details`}
                                            </h4>

                                            {productRequiresDetails && (
                                                <>
                                                    <InputGroup>
                                                        <TextInput
                                                            withAsterisk
                                                            label={t`First Name`}
                                                            placeholder={t`First name`}
                                                            {...form.getInputProps(`products.${productIndex}.first_name`)}
                                                        />
                                                        <TextInput
                                                            withAsterisk
                                                            label={t`Last Name`}
                                                            placeholder={t`Last Name`}
                                                            {...form.getInputProps(`products.${productIndex}.last_name`)}
                                                        />
                                                    </InputGroup>

                                                    <TextInput
                                                        withAsterisk
                                                        label={t`Email Address`}
                                                        placeholder={t`Email Address`}
                                                        {...form.getInputProps(`products.${productIndex}.email`)}
                                                    />
                                                </>
                                            )}

                                            {productQuestions &&
                                                <CheckoutProductQuestions
                                                    index={productIndex}
                                                    product={product}
                                                    form={form}
                                                    questions={productQuestions}/>}
                                        </Card>
                                    </>
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
            </CheckoutContent>
            <CheckoutFooter
                isLoading={mutation.isPending}
                buttonContent={order?.is_payment_required ? (
                    <Group gap={'10px'}>
                        <div style={{fontWeight: "bold"}}>
                            {t`Continue`}
                        </div>
                        <div style={{fontSize: 14}}>
                            {formatCurrency(order.total_gross, order.currency)}
                        </div>
                        <div style={{fontSize: 14, fontWeight: 500}}>
                            {order.currency}
                        </div>
                    </Group>
                ) : t`Complete Order`}
                event={event as Event}
                order={order as Order}
            />
        </form>
    );
}

export default CollectInformation;
