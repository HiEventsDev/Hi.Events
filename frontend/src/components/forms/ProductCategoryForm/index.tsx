import {UseFormReturnType} from "@mantine/form";
import {ProductCategory} from "../../../types.ts";
import {Switch, TextInput} from "@mantine/core";
import {Editor} from "../../common/Editor";
import {t} from "@lingui/macro";

interface ProductCategoryFormProps {
    form: UseFormReturnType<ProductCategory>,
}

export const ProductCategoryForm = ({form}: ProductCategoryFormProps) => {
    return (
        <>
            <TextInput
                label={t`Name`}
                placeholder={t`Name`}
                description={t`This is the name of the category that will be displayed on the event page.`}
                required
                {...form.getInputProps("name")}
            />

            <TextInput
                label={t`No products message`}
                description={t`A message to display when there are no products in this category.`}
                {...form.getInputProps("no_products_message")}
            />

            <Editor
                label={t`Description`}
                description={t`An optional description of this category to display on the event page.`}
                onChange={(value) => form.setFieldValue("description", value)}
                value={form.values.description || ""}
                maxLength={5000}
            />

            <Switch
                label={t`Hide this category?`}
                description={t`If checked, this category will be hidden from the public.`}
                {...form.getInputProps("is_hidden", {type: "checkbox"})}
            />
        </>
    );
}
