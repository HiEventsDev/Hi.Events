import {Alert, Box, Divider, Switch, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {CheckInListRequest, ProductCategory, ProductType} from "../../../types.ts";
import {InputGroup} from "../../common/InputGroup";
import {ProductSelector} from "../../common/ProductSelector";
import {useEffect, useMemo} from "react";
import {IconEyeOff, IconInfoCircle} from "@tabler/icons-react";

interface CheckInListFormProps {
    form: UseFormReturnType<CheckInListRequest>;
    productCategories: ProductCategory[];
}

export const CheckInListForm = ({form, productCategories}: CheckInListFormProps) => {
    const tickets = useMemo(() => {
        return productCategories
            .flatMap(category => category.products || [])
            .filter(product => product.product_type === ProductType.Ticket);
    }, [productCategories]);

    useEffect(() => {
        if (tickets.length === 1 && (!form.values.product_ids || form.values.product_ids.length === 0)) {
            form.setFieldValue('product_ids', [String(tickets[0].id)]);
        }
    }, [tickets]);

    return (
        <>
            <Alert mb={20} icon={<IconInfoCircle size={16}/>} color="blue" variant="light">
                {t`Check-in lists let you control entry across days, areas, or ticket types. You can share a secure check-in link with staff — no account required.`}
            </Alert>

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
                label={t`Description for check-in staff`}
                placeholder={t`Add a description for this check-in list`}
                description={t`Visible to check-in staff only. Shown to them the first time they open the check-in page.`}
                minRows={3}
                autosize
            />

            <InputGroup>
                <TextInput
                    {...form.getInputProps('activates_at')}
                    type="datetime-local"
                    label={t`Activation date`}
                    description={t`When check-in opens`}
                />
                <TextInput
                    {...form.getInputProps('expires_at')}
                    type="datetime-local"
                    label={t`Expiration date`}
                    description={t`When check-in closes`}
                />
            </InputGroup>

            <Divider
                mt="md"
                mb="md"
                label={
                    <Box style={{display: "flex", alignItems: "center", gap: 6}}>
                        <IconEyeOff size={14}/>
                        <span>{t`Public link visibility`}</span>
                    </Box>
                }
                labelPosition="left"
            />

            <Alert mb="sm" color="gray" variant="light">
                <Trans>
                    These toggles only apply to people opening the check-in link while <b>not logged in</b>. Logged-in team members always see all attendee data.
                </Trans>
            </Alert>

            <Switch
                mb="sm"
                {...form.getInputProps('public_show_attendee_notes', {type: 'checkbox'})}
                label={t`Show attendee notes`}
                description={t`Internal notes added to the attendee's ticket`}
            />

            <Switch
                mb="sm"
                {...form.getInputProps('public_show_question_answers', {type: 'checkbox'})}
                label={t`Show question answers`}
                description={t`Answers the attendee provided at checkout`}
            />

            <Switch
                mb="sm"
                {...form.getInputProps('public_show_order_details', {type: 'checkbox'})}
                label={t`Show order details`}
                description={t`Order number, purchase date, total paid`}
            />
        </>
    );
}
