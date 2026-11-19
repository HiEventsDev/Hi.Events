import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {Button, Text, TextInput} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {bulkUnsubscribeContactsConsequence, deleteContactsConsequence} from "../../utilites/contactConfirmations.ts";
import {Notice} from "../Notice";

const TYPED_CONFIRMATION_THRESHOLD = 25;

export const BulkConfirmModal = ({action, count, changedCount, isPending, onConfirm, onClose}: {
    action: 'UNSUBSCRIBE' | 'DELETE',
    count: number,
    changedCount: boolean,
    isPending: boolean,
    onConfirm: () => void,
    onClose: () => void,
}) => {
    const [typed, setTyped] = useState('');
    const needsTypedCount = count > TYPED_CONFIRMATION_THRESHOLD;
    const isConfirmed = !needsTypedCount || typed.trim() === String(count);
    const isDelete = action === 'DELETE';

    return (
        <Modal
            opened
            onClose={onClose}
            size="md"
            heading={isDelete
                ? plural(count, {one: 'Delete # contact?', other: 'Delete # contacts?'})
                : plural(count, {one: 'Unsubscribe # contact?', other: 'Unsubscribe # contacts?'})}
        >
            {changedCount && (
                <Notice>
                    {plural(count, {
                        one: 'This selection has changed. It now has # contact.',
                        other: 'This selection has changed. It now has # contacts.',
                    })}
                </Notice>
            )}
            <Text size="sm">
                {isDelete ? deleteContactsConsequence() : bulkUnsubscribeContactsConsequence()}
            </Text>
            {needsTypedCount && (
                <TextInput
                    mt="md"
                    label={t`Type ${count} to confirm`}
                    value={typed}
                    onChange={event => setTyped(event.currentTarget.value)}
                    inputMode="numeric"
                    autoComplete="off"
                    data-autofocus
                />
            )}
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button color="red" onClick={onConfirm} loading={isPending} disabled={!isConfirmed} data-testid="audience-bulk-confirm">
                    {isDelete
                        ? plural(count, {one: 'Delete # contact', other: 'Delete # contacts'})
                        : plural(count, {one: 'Unsubscribe # contact', other: 'Unsubscribe # contacts'})}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
