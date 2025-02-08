import {GenericModalProps, ProductCategory} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {Alert, Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useCreateProductCategory} from "../../../mutations/useCreateProductCategory.ts";
import {ProductCategoryForm} from "../../forms/ProductCategoryForm";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {IconInfoCircle} from "@tabler/icons-react";

export const CreateProductCategoryModal = ({onClose}: GenericModalProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const {eventId} = useParams();
    const form = useForm<ProductCategory>(
        {
            initialValues: {
                name: '',
                description: '',
                is_hidden: false,
                no_products_message: t`No products available in this category.`,
            },
        },
    );

    const mutation = useCreateProductCategory();

    const handleCreate = (values: ProductCategory) => {
        mutation.mutateAsync({
            eventId: eventId,
            productCategoryData: values
        })
            .then(() => {
                onClose();
                form.reset();
                showSuccess(t`Category Created Successfully`);
            })
            .catch((error) => errorHandler(form, error));
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Create Category`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit((values) => handleCreate(values))}>
                <Alert
                    icon={<IconInfoCircle/>}
                    title={t`What is a Category?`}
                    mb={20}
                >
                    {t`Categories allow you to group products together. For example, you might have a category for "Tickets" and another for "Merchandise".`}
                </Alert>
                <ProductCategoryForm form={form}/>
                <Button type="submit" fullWidth disabled={mutation.isPending}>
                    {mutation.isPending ? t`Working...` : t`Create Category`}
                </Button>
            </form>
        </Modal>
    )
}
