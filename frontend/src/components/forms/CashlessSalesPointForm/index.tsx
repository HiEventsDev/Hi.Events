import {t} from "@lingui/macro";
import {PasswordInput, Switch, Text, Textarea, TextInput} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {useState} from "react";
import {IconBeer} from "@tabler/icons-react";
import {ProductCategory, ProductType, UpsertCashlessSalesPointRequest} from "../../../types.ts";
import {ProductSelector} from "../../common/ProductSelector";
import {InputGroup} from "../../common/InputGroup";
import {AdvancedOptions} from "../../common/AdvancedOptions";

interface CashlessSalesPointFormProps {
    form: UseFormReturnType<UpsertCashlessSalesPointRequest>;
    productCategories: ProductCategory[];
    pinHelpText: string;
}

export const CashlessSalesPointForm = ({form, productCategories, pinHelpText}: CashlessSalesPointFormProps) => {
    const [advancedOpen, setAdvancedOpen] = useState(
        () => !!(form.values.description || form.values.activates_at || form.values.expires_at),
    );

    return (
        <>
            <TextInput
                withAsterisk
                label={t`Name`}
                placeholder={t`Main bar`}
                {...form.getInputProps('name')}
            />

            <ProductSelector
                label={t`What can be sold here?`}
                placeholder={t`Select the products this sales point sells`}
                icon={<IconBeer size="1rem"/>}
                productCategories={productCategories}
                form={form}
                productFieldName="product_ids"
                includedProductTypes={[ProductType.General]}
                noProductsMessage={t`Create a general product, like a drink, to sell it here`}
            />

            <Text size="xs" c="dimmed" mt={4}>
                {t`Leave this empty for a top-up desk that only loads balances and sells nothing.`}
            </Text>

            <Switch
                mt="md"
                label={t`Staff here can top up balances`}
                description={t`Lets your team add funds to a ticket in exchange for cash or a card payment.`}
                {...form.getInputProps('allow_staff_topups', {type: 'checkbox'})}
            />

            <AdvancedOptions
                opened={advancedOpen}
                onToggle={() => setAdvancedOpen((open) => !open)}
                dataTestId="cashless-sales-point-advanced-toggle"
            >
                <PasswordInput
                    label={t`Access PIN`}
                    description={pinHelpText}
                    placeholder={t`At least 4 characters`}
                    {...form.getInputProps('access_pin')}
                />

                <Textarea
                    label={t`Description`}
                    placeholder={t`A note for your team`}
                    {...form.getInputProps('description')}
                />

                <InputGroup>
                    <TextInput
                        type="datetime-local"
                        label={t`Opens at`}
                        {...form.getInputProps('activates_at')}
                    />
                    <TextInput
                        type="datetime-local"
                        label={t`Closes at`}
                        {...form.getInputProps('expires_at')}
                    />
                </InputGroup>
            </AdvancedOptions>
        </>
    );
};
