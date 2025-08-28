import {UseFormReturnType} from "@mantine/form";
import {MultiSelect, TextInput} from "@mantine/core";
import {CreateApiKeyRequest} from "../../../types.ts";
import {t} from "@lingui/macro";

interface ApiKeyFormProps {
    form: UseFormReturnType<CreateApiKeyRequest>,
}

// TODO: translations
export const ApiKeyForm = ({form}: ApiKeyFormProps) => {
    return (
        <>
            <TextInput {...form.getInputProps('token_name')} label={`Token Name`} placeholder="my-python-script" required/>

            <MultiSelect
                placeholder={`All Permissions`}
                label={`What permissions should be granted to this API key? (Applies to all by default)`}
                searchable
                data={[
                    {
                        value: "users",
                        label: "Users"
                    },
                    {
                        value: "accounts",
                        label: "Accounts"
                    },
                    {
                        value: "organizers",
                        label: "Organizers"
                    },
                    {
                        value: "taxes-and-fees",
                        label: "Taxes and Fees"
                    },
                    {
                        value: "events",
                        label: "Events (All)"
                    },
                    {
                        value: "events-general",
                        label: "Events -> General"
                    },
                    {
                        value: "events-products",
                        label: "Events -> Products"
                    },
                    {
                        value: "events-stats",
                        label: "Events -> Stats"
                    },
                    {
                        value: "events-attendees",
                        label: "Events -> Attendees"
                    },
                    {
                        value: "events-orders",
                        label: "Events -> Orders"
                    },
                    {
                        value: "events-questions",
                        label: "Events -> Questions"
                    },
                    {
                        value: "events-images",
                        label: "Events -> Images"
                    },
                    {
                        value: "events-promo-codes",
                        label: "Events -> Promo Codes"
                    },
                    {
                        value: "events-messages",
                        label: "Events -> Messages"
                    },
                    {
                        value: "events-settings",
                        label: "Events -> Settings"
                    },
                    {
                        value: "events-capacity-assignments",
                        label: "Events -> Capacity Assignments"
                    },
                    {
                        value: "events-check-in-lists",
                        label: "Events -> Check-In Lists"
                    },
                    {
                        value: "events-reports",
                        label: "Events -> Reports"
                    },
                ]}
                {...form.getInputProps('abilities')}
            />

            <TextInput type={'datetime-local'}
                       {...form.getInputProps('expires_at')}
                       label={t`Expiry Date`}
            />
        </>
    );
};
