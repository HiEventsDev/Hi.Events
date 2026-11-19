import {useState} from "react";
import {t} from "@lingui/macro";
import {SideDrawerSection} from "../../../../components/common/SideDrawer";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {useSetCrmContactTags} from "../../mutations/useSetCrmContactTags.ts";
import {CrmContact} from "../../types.ts";
import {CrmTagSelect} from "../CrmTagSelect";
import {TagList} from "../TagList";
import classes from "./ContactDrawer.module.scss";

const MAX_CONTACT_TAGS = 50;

export const Tags = ({organizerId, contact, canEdit}: { organizerId: IdParam, contact: CrmContact, canEdit: boolean }) => {
    const setTags = useSetCrmContactTags();
    const [pendingIds, setPendingIds] = useState<number[] | null>(null);

    const handleChange = (tagIds: number[]) => {
        setPendingIds(tagIds);
        setTags.mutate({organizerId, contactId: contact.id, tagIds}, {
            onError: error => showError(firstApiError(error, t`We couldn't update the tags. Please try again.`)),
            onSettled: () => setPendingIds(null),
        });
    };

    if (!canEdit) {
        return (
            <SideDrawerSection title={t`Tags`}>
                {contact.tags.length === 0
                    ? <p className={classes.empty}>{t`No tags.`}</p>
                    : <TagList tags={contact.tags}/>}
            </SideDrawerSection>
        );
    }

    return (
        <SideDrawerSection title={t`Tags`}>
            <div className={classes.tags}>
                <CrmTagSelect
                    organizerId={organizerId}
                    value={pendingIds ?? contact.tags.map(tag => tag.id)}
                    onChange={handleChange}
                    maxValues={MAX_CONTACT_TAGS}
                />
            </div>
        </SideDrawerSection>
    );
};
