import React from 'react';
import {Modal, TextInput, Textarea, Group, Button} from '@mantine/core';
import {useForm} from '@mantine/form';
import {t} from '@lingui/macro';
import {Organizer} from '../../../types';
import classes from './ContactOrganizerModal.module.scss';

interface ContactOrganizerModalProps {
    opened: boolean;
    onClose: () => void;
    organizer: Organizer;
}

export const ContactOrganizerModal: React.FC<ContactOrganizerModalProps> = ({
    opened,
    onClose,
    organizer
}) => {
    const contactForm = useForm({
        initialValues: {
            name: '',
            email: '',
            message: '',
        },
    });

    const handleContactSubmit = (values: any) => {
        // Handle contact form submission
        console.log('Contact form:', values);
        onClose();
        contactForm.reset();
    };

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            title={t`Contact ${organizer?.name || 'Organizer'}`}
            size="md"
            className={classes.contactModal}
        >
            <form onSubmit={contactForm.onSubmit(handleContactSubmit)}>
                <Group grow mb="md">
                    <TextInput
                        label={t`Your Name`}
                        placeholder={t`Enter your name`}
                        required
                        {...contactForm.getInputProps('name')}
                    />
                    <TextInput
                        label={t`Your Email`}
                        placeholder={t`Enter your email`}
                        required
                        type="email"
                        {...contactForm.getInputProps('email')}
                    />
                </Group>

                <Textarea
                    label={t`Message`}
                    placeholder={t`Write your message here...`}
                    required
                    minRows={4}
                    mb="md"
                    {...contactForm.getInputProps('message')}
                />

                <Group justify="flex-end">
                    <Button
                        variant="subtle"
                        onClick={onClose}
                        className={classes.cancelButton}
                    >
                        {t`Cancel`}
                    </Button>
                    <Button
                        type="submit"
                        className={classes.submitButton}
                    >
                        {t`Send Message`}
                    </Button>
                </Group>
            </form>
        </Modal>
    );
};