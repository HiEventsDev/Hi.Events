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
import {showError, showSuccess} from "../../../utilites/notifications.tsx";

interface CreateProductModalProps extends GenericModalProps {
    selectedCategoryId?: IdParam;
}

export const CreateProductModal = ({onClose, selectedCategoryId = undefined}: CreateProductModalProps) => {
    const {eventId} = useParams();
    const {data: taxesAndFees, isFetched: taxesAndFeesLoaded} = useGetTaxesAndFees();
    const createProductMutation = useCreateProduct();
    const form = useForm<Product>({
        initialValues: {
            title: '',
            description: '',
            max_per_order: 100,
            min_per_order: 1,
            sale_start_date: '',
            sale_end_date: '',
            start_collapsed: false,
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            show_quantity_remaining: false,
            hide_when_sold_out: false,
            is_hidden_without_promo_code: false,
            type: ProductPriceType.Paid,
            product_type: ProductType.Ticket,
            tax_and_fee_ids: undefined,
            product_category_id: selectedCategoryId ? String(selectedCategoryId) : undefined,
            prices: [{
                price: 0,
                label: undefined,
                sale_end_date: undefined,
                sale_start_date: undefined,
                initial_quantity_available: undefined,
            }],
        },
    });

    const handleCreateProduct = (values: Product) => {
        createProductMutation.mutate({eventId, productData: values}, {
            onSuccess: () => {
                showSuccess(t`Successfully Created Product`);
                form.reset();
                onClose();
            },

            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                }
                showError(t`Unable to create product. Please check the your details`);
            }
        });
    }

    useEffect(() => {
        form.setFieldValue('tax_and_fee_ids', taxesAndFees
            ?.data
            ?.filter((item) => item.is_default)
            .map((item: TaxAndFee) => {
                return String(item.id);
            }) || []);
    }, [taxesAndFeesLoaded]);

    return (
        <Modal
            onClose={onClose}
            heading={t`Create Ticket or Product`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit((values) => handleCreateProduct(values))}>
                <ProductForm form={form}/>
                <Button type="submit" fullWidth disabled={createProductMutation.isPending}>
                    {createProductMutation.isPending ? t`Working...` : t`Create Product`}
                </Button>
            </form>
        </Modal>
    )
};
