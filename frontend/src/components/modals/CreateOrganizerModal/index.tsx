import {GenericModalProps, Organizer} from "../../../types.ts";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import {t} from "@lingui/macro";
import {Modal} from "../../common/Modal";
import {useNavigate} from "react-router";
import {IconInfoCircle} from "@tabler/icons-react";
import {Callout} from "../../common/Callout";

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
            <Callout icon={<IconInfoCircle/>} variant="info">
                {t`Create additional organizers to manage separate brands, departments, or event series under one account. Each organizer has its own events, settings, and public page.`}
            </Callout>
            <OrganizerCreateForm onSuccess={(organizer: Organizer) => {
                onClose();
                navigate(`/manage/organizer/${organizer.id}`);
            }}/>
        </Modal>
    )
}
