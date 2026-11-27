import {IdParam} from "../../../../../types.ts";
import {CrmSegmentDefinition} from "../../../types.ts";
import {CheckAddressesModal} from "../../CheckAddressesModal";
import {ImportContactsModal} from "../../ImportContactsModal";
import {ImportSuppressionsModal} from "../../ImportSuppressionsModal";
import {ManageTagsModal} from "../../ManageTagsModal";
import {SegmentModal} from "../../SegmentModal";
import {AddContactModal} from "./AddContactModal.tsx";

export type ContactsModal = 'add' | 'import' | 'suppressions' | 'check' | 'tags' | 'save';

interface ContactsModalsProps {
    organizerId: IdParam;
    modal: ContactsModal | null;
    timezone: string;
    filter: CrmSegmentDefinition | null;
    onOpenModal: (modal: ContactsModal | null) => void;
    onOpenContact: (contactId: number) => void;
    onSegmentSaved: (segmentId: number) => void;
}

export const ContactsModals = ({organizerId, modal, timezone, filter, onOpenModal, onOpenContact, onSegmentSaved}: ContactsModalsProps) => {
    const close = () => onOpenModal(null);
    const closeAndOpenContact = (contactId: number) => {
        close();
        onOpenContact(contactId);
    };

    return (
        <>
            {modal === 'tags' && <ManageTagsModal organizerId={organizerId} onClose={close}/>}

            {modal === 'import' && (
                <ImportContactsModal organizerId={organizerId} onImportSuppressions={() => onOpenModal('suppressions')} onClose={close}/>
            )}

            {modal === 'suppressions' && <ImportSuppressionsModal organizerId={organizerId} onClose={close}/>}

            {modal === 'check' && (
                <CheckAddressesModal
                    organizerId={organizerId}
                    timezone={timezone}
                    onOpenContact={closeAndOpenContact}
                    onClose={close}
                />
            )}

            {modal === 'save' && filter && (
                <SegmentModal
                    organizerId={organizerId}
                    initialDefinition={filter}
                    onClose={close}
                    onSaved={segment => onSegmentSaved(segment.id)}
                />
            )}

            {modal === 'add' && (
                <AddContactModal
                    organizerId={organizerId}
                    onClose={close}
                    onCreated={contact => closeAndOpenContact(contact.id)}
                />
            )}
        </>
    );
};
