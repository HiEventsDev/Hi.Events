import {GenericModalProps, Organizer} from "../../../types.ts";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import {t} from "@lingui/macro";
import {Modal} from "../../common/Modal";
import {useNavigate} from "react-router";
import {Alert} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";

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
            <Alert icon={<IconInfoCircle size={16}/>} variant="light" color="blue" mb="md">
                {t`Create additional organizers to manage separate brands, departments, or event series under one account. Each organizer has its own events, settings, and public page.`}
            </Alert>
            <OrganizerCreateForm onSuccess={(organizer: Organizer) => {
                onClose();
                navigate(`/manage/organizer/${organizer.id}`);
            }}/>
        </Modal>
    )
}
