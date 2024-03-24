import {GenericModalProps} from "../../../types.ts";
import {OrganizerForm} from "../../forms/OrganizerForm";
import {t} from "@lingui/macro";
import {Modal} from "../../common/Modal";

interface CreateOrganizerModalProps extends GenericModalProps {
    onClose: () => void;
}

export const CreateOrganizerModal = ({onClose}: CreateOrganizerModalProps) => {
    return (
        <Modal
            onClose={onClose}
            heading={t`Create Organizer`}
            opened
            size={'lg'}
        >
            <OrganizerForm/>
        </Modal>
    )
}