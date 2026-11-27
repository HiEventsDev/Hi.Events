import {useState} from "react";
import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {PhoneNumberInput} from "../../../../../components/common/PhoneNumberInput";

const STORAGE_KEY = 'audience-sms-test-number';

const storedNumber = (): string => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) ?? '';
    } catch {
        return '';
    }
};

const rememberNumber = (phone: string): void => {
    try {
        window.localStorage.setItem(STORAGE_KEY, phone);
    } catch {
        return;
    }
};

export const SmsTestModal = ({defaultCountry, isSending, onSend, onClose}: {
    defaultCountry: string | undefined,
    isSending: boolean,
    onSend: (phone: string) => void,
    onClose: () => void,
}) => {
    const [phone, setPhone] = useState(storedNumber);

    const send = () => {
        rememberNumber(phone);
        onSend(phone);
    };

    return (
        <Modal opened onClose={onClose} heading={t`Send a test text`} size="md">
            <PhoneNumberInput
                label={t`Mobile number`}
                description={t`The test uses your name for placeholders. Its opt-out link doesn't work; real texts get a personal one.`}
                defaultCountry={defaultCountry}
                value={phone}
                onChange={setPhone}
            />
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button onClick={send} loading={isSending} disabled={phone === ''}>{t`Send test`}</Button>
            </ModalFooter>
        </Modal>
    );
};
