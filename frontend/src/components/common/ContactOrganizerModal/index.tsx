import React from 'react';
import {Modal, TextInput, Textarea, Group, Button} from '@mantine/core';
import {useForm} from '@mantine/form';
import {t} from '@lingui/macro';
import {Organizer} from '../../../types';
import {useContactOrganizer} from '../../../mutations/useContactOrganizer';
import {showSuccess, showError} from '../../../utilites/notifications';
import classes from './ContactOrganizerModal.module.scss';
import {InputGroup} from "../InputGroup";

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
    const contactMutation = useContactOrganizer();
    
    const contactForm = useForm({
        initialValues: {
            name: '',
            email: '',
            message: '',
        },
        validateInputOnBlur: true,
        validate: {
            name: (value) => !value ? t`Name is required` : null,
            email: (value) => {
                if (!value) return t`Email is required`;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return t`Invalid email`;
                return null;
            },
            message: (value) => {
                if (value.length > 5000) return t`Message cannot exceed 5000 characters`;

                return !value ? t`Message is required` : null;
            },
        },
    });

    const handleContactSubmit = (values: typeof contactForm.values) => {
        contactMutation.mutate({
            organizerId: organizer.id,
            contactData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Your message has been sent successfully!`);
                onClose();
                contactForm.reset();
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Failed to send message. Please try again.`);
            },
        });
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
                <InputGroup>
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
                </InputGroup>

                <Textarea
                    label={t`Message`}
                    placeholder={t`Write your message here...`}
                    required
                    minRows={4}
                    autosize
                    maxLength={5001}
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
                        loading={contactMutation.isPending}
                        disabled={contactMutation.isPending}
                    >
                        {t`Send Message`}
                    </Button>
                </Group>
            </form>
        </Modal>
    );
};
