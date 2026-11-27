import {t} from "@lingui/macro";
import {Button, Switch, Textarea, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../../types.ts";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {CrmSignupFormRequest} from "../../../api/crm.client.ts";
import {useCreateCrmSignupForm, useUpdateCrmSignupForm} from "../../../mutations/useCrmSignupFormMutations.ts";
import {CrmSignupForm} from "../../../types.ts";
import {CrmTagSelect} from "../../CrmTagSelect";
import {SignupFormCard} from "../../SignupFormCard";
import classes from "./Forms.module.scss";

const MAX_TAGS = 10;

export const SignupFormModal = ({organizerId, form, otherFormOnOrganizerPage, savesNewFormAsPaused, onClose, onCreated}: {
    organizerId: IdParam,
    form?: CrmSignupForm,
    otherFormOnOrganizerPage?: CrmSignupForm,
    savesNewFormAsPaused: boolean,
    onClose: () => void,
    onCreated: (form: CrmSignupForm) => void,
}) => {
    const createForm = useCreateCrmSignupForm();
    const updateForm = useUpdateCrmSignupForm();
    const errorHandler = useFormErrorResponseHandler();
    const values = useForm({
        initialValues: {
            name: form?.name ?? t`Newsletter`,
            title: form?.title ?? t`Get updates`,
            description: form?.description ?? t`Be the first to hear about new events and ticket releases.`,
            button_label: form?.button_label ?? t`Subscribe`,
            success_message: form?.success_message ?? '',
            asks_for_name: form?.asks_for_name ?? false,
            privacy_url: form?.privacy_url ?? '',
            tag_ids: form?.tag_ids ?? [],
            show_on_organizer_page: form?.show_on_organizer_page ?? false,
        },
        validate: {
            name: value => value.trim() ? null : t`Give this form a name`,
            title: value => value.trim() ? null : t`Add a heading`,
            button_label: value => value.trim() ? null : t`Add a button label`,
            privacy_url: value => !value.trim() || /^https:\/\/\S+$/i.test(value.trim()) ? null : t`Enter a link that starts with https://`,
        },
    });
    const otherFormName = otherFormOnOrganizerPage?.name ?? '';

    const handleSubmit = (submitted: typeof values.values) => {
        const request: CrmSignupFormRequest = {
            name: submitted.name.trim(),
            status: form?.status ?? (savesNewFormAsPaused ? 'PAUSED' : 'ACTIVE'),
            title: submitted.title.trim(),
            description: submitted.description.trim() || null,
            button_label: submitted.button_label.trim(),
            success_message: submitted.success_message.trim() || null,
            asks_for_name: submitted.asks_for_name,
            privacy_url: submitted.privacy_url.trim() || null,
            tag_ids: submitted.tag_ids,
            show_on_organizer_page: submitted.show_on_organizer_page,
        };

        if (form) {
            updateForm.mutate({organizerId, formId: form.id, form: request}, {
                onSuccess: () => {
                    showSuccess(t`Form saved`);
                    onClose();
                },
                onError: error => errorHandler(values, error),
            });
            return;
        }

        createForm.mutate({organizerId, form: request}, {
            onSuccess: ({data}) => {
                onClose();
                if (data.status === 'PAUSED') {
                    showSuccess(t`Form saved as paused`);
                    return;
                }
                showSuccess(t`Form created`);
                onCreated(data);
            },
            onError: error => errorHandler(values, error),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={form ? t`Edit form` : t`New signup form`} size="xl">
            <form onSubmit={values.onSubmit(handleSubmit)}>
                <div className={classes.editor}>
                    <div className={classes.fields}>
                        <TextInput
                            {...values.getInputProps('name')}
                            label={t`Name`}
                            description={t`Only your team sees this`}
                            maxLength={80}
                            data-testid="signup-form-name"
                        />
                        <TextInput
                            {...values.getInputProps('title')}
                            label={t`Heading`}
                            maxLength={100}
                            data-testid="signup-form-title"
                        />
                        <Textarea
                            {...values.getInputProps('description')}
                            label={t`Description`}
                            autosize
                            minRows={2}
                            maxRows={4}
                            maxLength={280}
                        />
                        <TextInput
                            {...values.getInputProps('button_label')}
                            label={t`Button label`}
                            maxLength={40}
                        />
                        <Switch
                            {...values.getInputProps('asks_for_name', {type: 'checkbox'})}
                            label={t`Ask for their name`}
                            description={t`Adds optional first and last name fields`}
                        />
                        <CrmTagSelect
                            organizerId={organizerId}
                            value={values.values.tag_ids}
                            onChange={tagIds => values.setFieldValue('tag_ids', tagIds)}
                            label={t`Tag new subscribers`}
                            description={t`Added when they confirm, so you can find them later`}
                            maxValues={MAX_TAGS}
                            error={values.errors.tag_ids as string | undefined}
                        />
                        <TextInput
                            {...values.getInputProps('privacy_url')}
                            label={t`Privacy policy link`}
                            description={t`Optional. Shown under the form.`}
                            placeholder="https://"
                            maxLength={500}
                        />
                        <Textarea
                            {...values.getInputProps('success_message')}
                            label={t`Message after confirming`}
                            description={t`Shown once they click the link in the confirmation email`}
                            placeholder={t`You're subscribed. Thanks for joining.`}
                            autosize
                            minRows={2}
                            maxRows={4}
                            maxLength={280}
                        />
                        <Switch
                            {...values.getInputProps('show_on_organizer_page', {type: 'checkbox'})}
                            label={t`Show on my organizer page`}
                            description={otherFormOnOrganizerPage && values.values.show_on_organizer_page
                                ? t`This replaces "${otherFormName}", which is shown there now.`
                                : t`Adds the form under your upcoming events`}
                            data-testid="signup-form-organizer-page"
                        />
                    </div>
                    <div className={classes.preview}>
                        <span className={classes.previewLabel}>{t`Preview`}</span>
                        <SignupFormCard
                            form={{
                                title: values.values.title,
                                description: values.values.description || null,
                                button_label: values.values.button_label,
                                success_message: null,
                                asks_for_name: values.values.asks_for_name,
                                privacy_url: values.values.privacy_url || null,
                            }}
                        />
                    </div>
                </div>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={createForm.isPending || updateForm.isPending} data-testid="signup-form-save">
                        {form ? t`Save` : t`Create form`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
