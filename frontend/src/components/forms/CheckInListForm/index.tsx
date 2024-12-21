import {Textarea, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CheckInListRequest, ProductCategory, ProductType} from "../../../types.ts";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {InputGroup} from "../../common/InputGroup";
import {ProductSelector} from "../../common/ProductSelector";

interface CheckInListFormProps {
    form: UseFormReturnType<CheckInListRequest>;
    productCategories: ProductCategory[],
}

export const CheckInListForm = ({form, productCategories}: CheckInListFormProps) => {
    return (
        <>
            <TextInput
                {...form.getInputProps('name')}
                required
                label={t`Name`}
                placeholder={t`VIP check-in list`}
            />

            <ProductSelector
                label={t`Which tickets should be associated with this check-in list?`}
                placeholder={t`Select tickets`}
                productCategories={productCategories}
                form={form}
                productFieldName="product_ids"
                includedProductTypes={[ProductType.Ticket]}
            />

            <Textarea
                {...form.getInputProps('description')}
                label={<InputLabelWithHelp
                    label={t`Description for check-in staff`}
                    helpText={t`This description will be shown to the check-in staff`}
                />}
                placeholder={t`Add a description for this check-in list`}
            />

            <InputGroup>
                <TextInput
                    {...form.getInputProps('activates_at')}
                    type="datetime-local"
                    label={<InputLabelWithHelp
                        label={t`Activation date`}
                        helpText={t`No attendees will be able to check in before this date using this list`}
                    />}
                    placeholder={t`What date should this check-in list become active?`}
                />
                <TextInput
                    {...form.getInputProps('expires_at')}
                    type="datetime-local"
                    label={<InputLabelWithHelp
                        label={t`Expiration date`}
                        helpText={t`This list will no longer be available for check-ins after this date`}
                    />}
                    placeholder={t`When should this check-in list expire?`}
                />
            </InputGroup>
        </>
    );
}
