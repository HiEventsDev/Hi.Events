import {useState} from "react";
import {useLocation} from "react-router";
import {useGetActiveAnnouncements} from "../../../queries/useGetActiveAnnouncements";
import {useDismissAnnouncement} from "../../../mutations/useDismissAnnouncement";
import {Announcement} from "../../../api/announcement.client";
import {IdParam} from "../../../types";
import {AnnouncementBanner} from "./AnnouncementBanner";
import {AnnouncementModal} from "./AnnouncementModal";

const sessionHiddenIds = new Set<IdParam>();
const sessionClosedModalIds = new Set<IdParam>();

const AnnouncementDisplay = () => {
    const location = useLocation();
    const isAdminRoute = location.pathname.startsWith('/admin');
    const {data: announcements} = useGetActiveAnnouncements(!isAdminRoute);
    const dismissMutation = useDismissAnnouncement();
    const [, setRenderCount] = useState(0);
    const rerender = () => setRenderCount((count) => count + 1);

    if (isAdminRoute || !announcements?.length) {
        return null;
    }

    const isPending = (announcement: Announcement) => !sessionHiddenIds.has(announcement.id);
    const banner = announcements.filter(isPending).find((a) => a.display_type === 'BANNER');
    const modal = announcements.filter(isPending).find((a) => a.display_type === 'MODAL');
    const showModal = !!modal && !sessionClosedModalIds.has(modal.id);

    const dismiss = (announcement: Announcement) => {
        sessionHiddenIds.add(announcement.id);
        rerender();
        dismissMutation.mutate(announcement.id, {
            onError: () => {
                sessionHiddenIds.delete(announcement.id);
                rerender();
            },
        });
    };

    const closeModal = (announcement: Announcement) => {
        sessionClosedModalIds.add(announcement.id);
        rerender();
    };

    return (
        <>
            {banner && !showModal && (
                <AnnouncementBanner
                    announcement={banner}
                    onDismiss={() => dismiss(banner)}
                />
            )}
            {showModal && (
                <AnnouncementModal
                    announcement={modal}
                    onDismiss={() => dismiss(modal)}
                    onClose={() => closeModal(modal)}
                />
            )}
        </>
    );
};

export default AnnouncementDisplay;
