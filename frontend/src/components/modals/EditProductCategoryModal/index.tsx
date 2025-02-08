import {GenericModalProps, IdParam, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {LoadingMask} from "../../common/LoadingMask";
import {Button} from "@mantine/core";
import {useGetEventProductCategory} from "../../../queries/useGetProductCategory.ts";
import {useParams} from "react-router";
import {useEditProductCategory} from "../../../mutations/useEditProductCategory.ts";
import {useForm} from "@mantine/form";
import {useEffect} from "react";
import {ProductCategoryForm} from "../../forms/ProductCategoryForm";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";

export const EditProductCategoryModal = ({onClose, productCategoryId}: GenericModalProps & {
    productCategoryId: IdParam
}) => {
    const errorHandler = useFormErrorResponseHandler();
    const {eventId} = useParams();
    const {data: productCategory, isFetched} = useGetEventProductCategory(productCategoryId, eventId);
    const form = useForm<ProductCategory>({
        initialValues: {
            name: '',
            description: '',
            is_hidden: false,
        },
    });

    const mutation = useEditProductCategory();

    useEffect(() => {
        if (!productCategory) {
            return;
        }

        form.setValues({
            id: productCategory.id,
            name: productCategory.name,
            description: productCategory.description,
            is_hidden: productCategory.is_hidden,
            no_products_message: productCategory.no_products_message,
        });
    }, [isFetched]);

    const handleEditProduct = () => {
        mutation.mutate({
            eventId,
            productCategoryId,
            productCategoryData: form.values,
        }, {
            onError: (error) => errorHandler(form, error),
            onSuccess: () => {
                showSuccess(t`Product category updated successfully.`);
                onClose();
            },
        });
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Edit Product Category`}
            opened
        >
            <form onSubmit={form.onSubmit(handleEditProduct)}>
                <ProductCategoryForm form={form}/>
                <LoadingMask/>

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Edit Product Category`}
                </Button>
            </form>
        </Modal>
    )
}
