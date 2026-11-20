import {useForm} from "@mantine/form";
import {Select, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {useEffect} from "react";
import {useParams} from "react-router";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {SettingsBlock, SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {DayOfWeek} from "@mantine/dates";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {currencies} from "../../../../../../../data/currencies.ts";
import {timezones} from "../../../../../../../data/timezones.ts";
import {Editor} from "../../../../../common/Editor";
import {useUpdateOrganizer} from "../../../../../../mutations/useUpdateOrganizer.ts";

const Settings = () => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);
    const {data: organizerSettings} = useGetOrganizerSettings(organizerId);
    const organizerMutation = useUpdateOrganizer();
    const settingsMutation = useUpdateOrganizerSettings();
    const formErrorHandle = useFormErrorResponseHandler();
    const isSaving = organizerMutation.isPending || settingsMutation.isPending;
    const form = useForm({
        initialValues: {
            name: '',
            email: '',
            phone: '',
            website: '',
            description: '',
            currency: '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            first_day_of_week: '1',
        }
    });

    const daysOfWeek = [
        {value: '1', label: t`Monday`},
        {value: '2', label: t`Tuesday`},
        {value: '3', label: t`Wednesday`},
        {value: '4', label: t`Thursday`},
        {value: '5', label: t`Friday`},
        {value: '6', label: t`Saturday`},
        {value: '0', label: t`Sunday`},
    ];

    const handleSubmit = ({first_day_of_week, ...organizerData}: typeof form.values) => {
        Promise.all([
            organizerMutation.mutateAsync({organizerId, organizerData}),
            settingsMutation.mutateAsync({
                organizerId,
                organizerSettings: {first_day_of_week: Number(first_day_of_week) as DayOfWeek},
            }),
        ])
            .then(() => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Organizer`);
            })
            .catch((error) => formErrorHandle(form, error));
    }

    useEffect(() => {
        if (!organizer) {
            return;
        }
        const values = {
            ...form.getValues(),
            name: String(organizer.name),
            email: String(organizer.email),
            currency: String(organizer.currency),
            timezone: String(organizer.timezone),
            phone: String(organizer.phone || ''),
            website: String(organizer.website || ''),
            description: String(organizer.description || ''),
        };
        form.setInitialValues(values);
        form.setValues(values);
    }, [organizer]);

    useEffect(() => {
        if (organizerSettings) {
            const values = {
                ...form.getValues(),
                first_day_of_week: String(organizerSettings.first_day_of_week ?? 1),
            };
            form.setInitialValues(values);
            form.setValues(values);
        }
    }, [organizerSettings]);

    return (
        <SettingsPage
            title={t`Basic Information`}
            description={t`General information about your organizer`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={isSaving}>
                        <SettingsRow
                            label={t`Organizer Name`}
                            description={t`This is the name of your organizer that will be displayed to your users.`}
                            htmlFor="organizer-basic-name"
                            required
                        >
                            <TextInput
                                id="organizer-basic-name"
                                {...form.getInputProps('name')}
                                required
                                placeholder={t`Awesome Organizer Ltd.`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Email`}
                            description={t`This will be used for notifications and communication with your users.`}
                            htmlFor="organizer-basic-email"
                            required
                        >
                            <TextInput
                                id="organizer-basic-email"
                                {...form.getInputProps('email')}
                                required
                                placeholder={t`hello@awesome-events.com`}
                            />
                        </SettingsRow>
                        <SettingsBlock>
                            <Editor
                                label={t`Description`}
                                value={form.values.description || ''}
                                editorType={'simple'}
                                description={t`A short description of your organizer that will be displayed to your users.`}
                                onChange={(value) => form.setFieldValue('description', value)}
                                maxLength={1000}
                            />
                        </SettingsBlock>
                        <SettingsRow label={t`Phone`} htmlFor="organizer-basic-phone">
                            <TextInput
                                id="organizer-basic-phone"
                                {...form.getInputProps('phone')}
                                placeholder={t`+1 234 567 890`}
                            />
                        </SettingsRow>
                        <SettingsRow label={t`Website`} htmlFor="organizer-basic-website">
                            <TextInput
                                id="organizer-basic-website"
                                {...form.getInputProps('website')}
                                type={'url'}
                                placeholder={t`https://awesome-events.com`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Currency`}
                            description={t`The default currency for your events.`}
                            htmlFor="organizer-basic-currency"
                            required
                        >
                            <Select
                                id="organizer-basic-currency"
                                {...form.getInputProps('currency')}
                                searchable
                                required
                                data={Object.entries(currencies).map(([key, value]) => ({
                                    value: value,
                                    label: key,
                                }))}
                                placeholder={t`EUR`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Timezone`}
                            description={t`The default timezone for your events.`}
                            htmlFor="organizer-basic-timezone"
                            required
                        >
                            <Select
                                id="organizer-basic-timezone"
                                {...form.getInputProps('timezone')}
                                searchable
                                required
                                data={timezones}
                                placeholder={t`UTC`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`First day of the week`}
                            description={t`The day your calendars and date pickers start on.`}
                            htmlFor="organizer-basic-first-day-of-week"
                            required
                        >
                            <Select
                                id="organizer-basic-first-day-of-week"
                                {...form.getInputProps('first_day_of_week')}
                                required
                                allowDeselect={false}
                                data={daysOfWeek}
                            />
                        </SettingsRow>
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={isSaving}
                        onDiscard={form.reset}
                    />
                </form>
            </SettingsSection>
        </SettingsPage>
    );
}

export default Settings;
