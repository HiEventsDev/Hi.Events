import {Modal} from "../../common/Modal";
import {GenericModalProps, Product, ProductCategory, ProductType} from "../../../types.ts";
import {Button} from "../../common/Button";
import {useNavigate, useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {LoadingOverlay, NumberInput, Switch, Text, TextInput, Card, Group, Badge, Stack} from "@mantine/core";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useCreateManualOrder} from "../../../mutations/useCreateManualOrder.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";
import {getProductsFromEvent} from "../../../utilites/helpers.ts";
import {CreateManualOrderPayload} from "../../../api/order.client.ts";

interface ManualOrderFormValues {
    first_name: string;
    last_name: string;
    email: string;
    send_confirmation_email: boolean;
    notes: string;
    quantities: Record<string, number>;
}

export const CreateManualOrderModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const mutation = useCreateManualOrder();
    const navigate = useNavigate();
    const eventProducts = getProductsFromEvent(event);
    const ticketProducts = eventProducts?.filter(p => p.product_type === ProductType.Ticket || p.product_type === ProductType.General);
    const hasProducts = ticketProducts && ticketProducts.length > 0;

    const form = useForm<ManualOrderFormValues>({
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            send_confirmation_email: true,
            notes: '',
            quantities: {},
        },
    });

    const handleSubmit = (values: ManualOrderFormValues) => {
        const products = Object.entries(values.quantities)
            .filter(([, qty]) => qty > 0)
            .map(([key, qty]) => {
                const [productId, priceId] = key.split('_').map(Number);
                return {
                    product_id: productId,
                    quantities: [{price_id: priceId, quantity: qty}],
                };
            });

        if (products.length === 0) {
            form.setFieldError('quantities', t`Please select at least one product.`);
            return;
        }

        const payload: CreateManualOrderPayload = {
            first_name: values.first_name,
            last_name: values.last_name,
            email: values.email,
            send_confirmation_email: values.send_confirmation_email,
            products,
            notes: values.notes || null,
        };

        mutation.mutate({
            eventId: eventId!,
            payload,
        }, {
            onSuccess: () => {
                showSuccess(t`Order created successfully`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    };

    if (!event?.product_categories) {
        return <LoadingOverlay visible/>;
    }

    if (isEventFetched && !hasProducts) {
        return (
            <Modal opened onClose={onClose} heading={t`Create Order`}>
                <p>{t`You must create a product before you can manually create an order.`}</p>
                <Button
                    fullWidth
                    variant={'light'}
                    onClick={() => navigate(`/manage/event/${eventId}/products`)}
                >
                    {t`Manage Products`}
                </Button>
            </Modal>
        );
    }

    return (
        <Modal opened onClose={onClose} heading={t`Create Order`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <InputGroup>
                    <TextInput
                        {...form.getInputProps('first_name')}
                        label={t`First name`}
                        placeholder={t`Patrick`}
                        required
                    />
                    <TextInput
                        {...form.getInputProps('last_name')}
                        label={t`Last name`}
                        placeholder={t`Johnson`}
                        required
                    />
                </InputGroup>

                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Email address`}
                    placeholder={t`patrick@acme.com`}
                    required
                    type="email"
                />

                <Text fw={500} size="sm" mt="lg" mb="xs">{t`Products`}</Text>
                {form.errors.quantities && (
                    <Text c="red" size="xs" mb="xs">{form.errors.quantities}</Text>
                )}

                <Stack gap="xs">
                    {(event.product_categories as ProductCategory[])?.map((category) => (
                        category.products
                            ?.filter(p => p.product_type === ProductType.Ticket || p.product_type === ProductType.General)
                            ?.map((product: Product) => (
                                product.prices?.map((price) => {
                                    const key = `${product.id}_${price.id}`;
                                    const label = product.prices && product.prices.length > 1
                                        ? `${product.title} — ${price.label}`
                                        : product.title;

                                    return (
                                        <Card key={key} withBorder padding="xs">
                                            <Group justify="space-between" wrap="nowrap">
                                                <div style={{flex: 1, minWidth: 0}}>
                                                    <Text size="sm" fw={500} truncate>
                                                        {label}
                                                    </Text>
                                                    <Group gap="xs">
                                                        <Badge size="xs" variant="light">
                                                            {event.currency} {price.price?.toFixed(2)}
                                                        </Badge>
                                                        {price.is_sold_out && (
                                                            <Badge size="xs" color="red" variant="light">
                                                                {t`Sold out`}
                                                            </Badge>
                                                        )}
                                                    </Group>
                                                </div>
                                                <NumberInput
                                                    {...form.getInputProps(`quantities.${key}`)}
                                                    min={0}
                                                    max={product.max_per_order || 100}
                                                    step={1}
                                                    w={90}
                                                    size="xs"
                                                    placeholder="0"
                                                    disabled={price.is_sold_out}
                                                />
                                            </Group>
                                        </Card>
                                    );
                                })
                            ))
                    ))}
                </Stack>

                <TextInput
                    {...form.getInputProps('notes')}
                    label={t`Order notes`}
                    placeholder={t`Internal notes about this order...`}
                    mt="md"
                />

                <Switch
                    mt="md"
                    label={t`Send order confirmation and ticket email`}
                    {...form.getInputProps('send_confirmation_email', {type: 'checkbox'})}
                />

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Creating order...` : t`Create Order`}
                </Button>
            </form>
        </Modal>
    );
};
