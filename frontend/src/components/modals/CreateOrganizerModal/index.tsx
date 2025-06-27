import {GenericModalProps, Organizer} from "../../../types.ts";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import {t} from "@lingui/macro";
import {Modal} from "../../common/Modal";
import {useNavigate} from "react-router";

interface CreateOrganizerModalProps extends GenericModalProps {
    onClose: () => void;
}

export const CreateOrganizerModal = ({onClose}: CreateOrganizerModalProps) => {
    const navigate = useNavigate();
    return (
        <Modal
            onClose={onClose}
            heading={t`Create Organizer`}
            opened
            size={'lg'}
            modalHeader={'branded'}
        >
            <OrganizerCreateForm onSuccess={(organizer: Organizer) => {
                onClose();
                navigate(`/manage/organizer/${organizer.id}`);
            }}/>
        </Modal>
    )
}
