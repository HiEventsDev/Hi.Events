import {t} from "@lingui/macro";
import {Alert, Button, Checkbox, Group, Modal, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconInfoCircle} from "@tabler/icons-react";
import {useState} from "react";
import {Attendee} from "../../../../../types";
import classes from "./EditAttendeeModal.module.scss";
import {InputGroup} from "../../../../common/InputGroup";

interface EditAttendeeModalProps {
    opened: boolean;
    onClose: () => void;
    attendee: Attendee;
    onSuccess: (values: any, resendEmail: boolean) => void;
}

export const EditAttendeeModal = ({
                                      opened,
                                      onClose,
                                      attendee,
                                      onSuccess,
                                  }: EditAttendeeModalProps) => {
    const [resendEmail, setResendEmail] = useState(false);

    const form = useForm({
        initialValues: {
            first_name: attendee.first_name,
            last_name: attendee.last_name,
            email: attendee.email,
        },
        validate: {
            first_name: (value) => !value ? t`First name is required` : null,
            last_name: (value) => !value ? t`Last name is required` : null,
            email: (value) => {
                if (!value) return t`Email is required`;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return t`Invalid email`;
                return null;
            },
        },
    });

    const emailChanged = form.values.email !== attendee.email;

    const handleSubmit = (values: typeof form.values) => {
        onSuccess(values, emailChanged || resendEmail);
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            title={t`Edit Attendee Details`}
            size="md"
            className={classes.modal}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <div>
                    <InputGroup>
                        <TextInput
                            label={t`First Name`}
                            placeholder={t`Enter first name`}
                            required
                            {...form.getInputProps('first_name')}
                        />

                        <TextInput
                            label={t`Last Name`}
                            placeholder={t`Enter last name`}
                            required
                            {...form.getInputProps('last_name')}
                        />
                    </InputGroup>

                    <TextInput
                        label={t`Email`}
                        placeholder={t`Enter email`}
                        required
                        type="email"
                        {...form.getInputProps('email')}
                    />

                    {emailChanged && (
                        <Alert mb={20} icon={<IconInfoCircle size={16}/>} color="blue">
                            <Text size="sm">
                                {t`The email address has been changed. The attendee will receive a new ticket at the updated email address.`}
                            </Text>
                        </Alert>
                    )}

                    {!emailChanged && (
                        <Checkbox
                            label={t`Resend ticket email`}
                            checked={resendEmail}
                            mb={20}
                            onChange={(e) => setResendEmail(e.currentTarget.checked)}
                        />
                    )}

                    <Group justify="flex-end" gap="sm">
                        <Button variant="default" onClick={onClose}>
                            {t`Cancel`}
                        </Button>
                        <Button type="submit">
                            {t`Save`}
                        </Button>
                    </Group>
                </div>
            </form>
        </Modal>
    );
};
