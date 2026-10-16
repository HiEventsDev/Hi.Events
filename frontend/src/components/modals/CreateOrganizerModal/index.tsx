import {Button, Select, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {Modal, ModalFooter} from "../../common/Modal";
import {InputGroup} from "../../common/InputGroup";
import {GenericModalProps, Organizer} from "../../../types.ts";
import {organizerCurrencyOptions, useCreateOrganizerForm} from "../../forms/OrganizerForm";
import {timezones} from "../../../../data/timezones.ts";
import classes from "./CreateOrganizerModal.module.scss";

export const CreateOrganizerModal = ({onClose}: GenericModalProps) => {
    const navigate = useNavigate();
    const {form, handleSubmit, isPending, isReady} = useCreateOrganizerForm({
        prefillName: false,
        onSuccess: (organizer: Organizer) => {
            onClose();
            navigate(`/manage/organizer/${organizer.id}`);
        },
    });

    return (
        <Modal opened onClose={onClose} heading={t`Create organizer`} size={540}>
            <p className={classes.intro}>
                {t`Use a separate organizer for each brand or venue you sell tickets under.`}
            </p>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={isPending || !isReady}>
                    <TextInput
                        {...form.getInputProps('name')}
                        label={t`Organizer name`}
                        placeholder={t`Awesome Events Ltd.`}
                        description={t`Shown on your public page, tickets and emails.`}
                        required
                        data-autofocus
                    />
                    <TextInput
                        {...form.getInputProps('email')}
                        type="email"
                        label={t`Contact email`}
                        placeholder={t`hello@awesome-events.com`}
                        description={t`Used for notifications and when attendees contact you.`}
                        required
                    />
                    <InputGroup>
                        <Select
                            {...form.getInputProps('currency')}
                            searchable
                            allowDeselect={false}
                            data={organizerCurrencyOptions}
                            label={t`Currency`}
                            placeholder={t`Select currency`}
                            required
                        />
                        <Select
                            {...form.getInputProps('timezone')}
                            searchable
                            allowDeselect={false}
                            data={timezones}
                            label={t`Timezone`}
                            placeholder={t`Select timezone`}
                            required
                        />
                    </InputGroup>
                </fieldset>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={isPending} disabled={!isReady} data-testid="create-organizer-submit">
                        {t`Create organizer`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
