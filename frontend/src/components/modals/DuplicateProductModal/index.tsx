import {Button} from "@mantine/core";
import {GenericModalProps, IdParam, Product, ProductPriceType, ProductType, TaxAndFee} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {Modal} from "../../common/Modal";
import {ProductForm} from "../../forms/ProductForm";
import {useEffect} from "react";
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import {t} from "@lingui/macro";
import {useCreateProduct} from "../../../mutations/useCreateProduct.ts";
import {useGetProduct} from "../../../queries/useGetProduct.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";

interface DuplicateProductModalProps extends GenericModalProps {
    originalProductId: IdParam;
}

export const DuplicateProductModal = ({onClose, originalProductId}: DuplicateProductModalProps) => {
    const {eventId} = useParams();
    const {data: taxesAndFees, isFetched: taxesAndFeesLoaded} = useGetTaxesAndFees();
    const {data: originalProduct} = useGetProduct(eventId, originalProductId);
    const createProductMutation = useCreateProduct();

    const form = useForm<Product>({
        initialValues: {
            title: "",
            description: "",
            max_per_order: 100,
            min_per_order: 1,
            sale_start_date: "",
            sale_end_date: "",
            start_collapsed: false,
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            show_quantity_remaining: false,
            hide_when_sold_out: false,
            is_hidden_without_promo_code: false,
            type: ProductPriceType.Paid,
            product_type: ProductType.Ticket,
            tax_and_fee_ids: undefined,
            product_category_id: undefined,
            prices: [{
                price: 0,
                label: undefined,
                sale_end_date: undefined,
                sale_start_date: undefined,
                initial_quantity_available: undefined,
            }],
        },
    });

    useEffect(() => {
        if (!originalProduct || !taxesAndFeesLoaded) {
            return;
        }

        form.setValues({
            title: `${originalProduct.title} (Copy)`,
            description: originalProduct.description,
            max_per_order: originalProduct.max_per_order,
            min_per_order: originalProduct.min_per_order,
            sale_start_date: originalProduct.sale_start_date,
            sale_end_date: originalProduct.sale_end_date,
            start_collapsed: originalProduct.start_collapsed,
            hide_before_sale_start_date: originalProduct.hide_before_sale_start_date,
            hide_after_sale_end_date: originalProduct.hide_after_sale_end_date,
            show_quantity_remaining: originalProduct.show_quantity_remaining,
            hide_when_sold_out: originalProduct.hide_when_sold_out,
            is_hidden_without_promo_code: originalProduct.is_hidden_without_promo_code,
            is_hidden: originalProduct.is_hidden,
            type: originalProduct.type,
            tax_and_fee_ids: originalProduct.taxes_and_fees?.map(t => String(t.id)) ?? [],
            product_type: originalProduct.product_type,
            product_category_id: originalProduct.product_category_id,
            prices: originalProduct.prices?.map(price => ({
                price: price.price,
                label: price.label,
                sale_start_date: price.sale_start_date,
                sale_end_date: price.sale_end_date,
                initial_quantity_available: price.initial_quantity_available,
                is_hidden: price.is_hidden,
            })),
        });
    }, [originalProduct]);

    useEffect(() => {
        if (taxesAndFeesLoaded) {
            form.setFieldValue("tax_and_fee_ids", taxesAndFees?.data?.filter(item => item.is_default).map((item: TaxAndFee) => String(item.id)) || []);
        }
    }, [taxesAndFeesLoaded]);

    const handleDuplicateProduct = (values: Product) => {
        createProductMutation.mutate({eventId, productData: values}, {
            onSuccess: () => {
                showSuccess(t`Successfully Duplicated Product`);
                form.reset();
                onClose();
            },
            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                }
                showError(t`Unable to duplicate product. Please check the your details`);
            },
        });
    };

    return (
        <Modal onClose={onClose} heading={t`Duplicate Product`} opened size={"lg"} withCloseButton>
            <form onSubmit={form.onSubmit(handleDuplicateProduct)}>
                <ProductForm form={form}/>
                <Button type="submit" fullWidth disabled={createProductMutation.isPending}>
                    {createProductMutation.isPending ? t`Working...` : t`Duplicate Product`}
                </Button>
            </form>
        </Modal>
    );
};
