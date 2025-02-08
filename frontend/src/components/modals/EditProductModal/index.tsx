import {Button} from "@mantine/core";
import {GenericModalProps, IdParam, Product, ProductPriceType, ProductType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {ProductForm} from "../../forms/ProductForm";
import {Modal} from "../../common/Modal";
import {useUpdateProduct} from "../../../mutations/useUpdateProduct.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import {useGetProduct} from "../../../queries/useGetProduct.ts";
import {LoadingMask} from "../../common/LoadingMask";
import {utcToTz} from "../../../utilites/dates.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";

export const EditProductModal = ({onClose, productId}: GenericModalProps & { productId: IdParam }) => {
    const {eventId} = useParams();
    const {data: product} = useGetProduct(eventId, productId);
    const {data: event} = useGetEvent(eventId);
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm<Product>({
        initialValues: {
            title: '',
            description: '',
            max_per_order: 100,
            min_per_order: 1,
            sale_start_date: undefined,
            sale_end_date: undefined,
            hide_before_sale_start_date: undefined,
            hide_after_sale_end_date: undefined,
            start_collapsed: undefined,
            show_quantity_remaining: undefined,
            hide_when_sold_out: undefined,
            is_hidden_without_promo_code: undefined,
            type: ProductPriceType.Paid,
            tax_and_fee_ids: [],
            prices: [],
            product_type: ProductType.Ticket,
            product_category_id: undefined,
        },
    });

    const mutation = useUpdateProduct();

    useEffect(() => {
        if (!product || !event) {
            return;
        }

        form.setValues({
            id: product.id,
            title: product.title,
            description: product.description,
            max_per_order: product.max_per_order ?? 0,
            min_per_order: product.min_per_order ?? 0,
            sale_start_date: utcToTz(product.sale_start_date, event.timezone),
            sale_end_date: utcToTz(product.sale_end_date, event.timezone),
            hide_before_sale_start_date: product.hide_before_sale_start_date,
            hide_after_sale_end_date: product.hide_after_sale_end_date,
            show_quantity_remaining: product.show_quantity_remaining,
            start_collapsed: product.start_collapsed,
            hide_when_sold_out: product.hide_when_sold_out,
            is_hidden_without_promo_code: product.is_hidden_without_promo_code,
            type: product.type,
            tax_and_fee_ids: product.taxes_and_fees?.map(t => String(t.id)) ?? [],
            is_hidden: product.is_hidden,
            product_type: product.product_type,
            product_category_id: String(product.product_category_id),
            prices: product.prices?.map(p => ({
                price: p.price ?? 0,
                label: p.label,
                sale_start_date: utcToTz(p.sale_start_date, event.timezone),
                sale_end_date: utcToTz(p.sale_end_date, event.timezone),
                initial_quantity_available: p.initial_quantity_available ?? undefined,
                id: p.id,
                is_hidden: p.is_hidden,
            })) ?? [],
        });
    }, [product, event]);

    const handleEditProduct = (product: Product) => {
        mutation.mutate({
            productData: product,
            eventId: eventId,
            productId: productId
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully updated product ` + product.title);
                form.reset();
                onClose();
            },
            onError: (error) => errorHandler(form, error)
        })
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Edit Product`}
            opened
        >
            <form onSubmit={form.onSubmit(handleEditProduct)}>
                <ProductForm product={product} form={form}/>
                <LoadingMask/>

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Edit Product`}
                </Button>
            </form>
        </Modal>
    )
};
