import {useNavigate} from "react-router";
import {CellLink} from "../../../common/TanStackTable/cells.tsx";

export const EventLink = ({eventId, children}: { eventId: number; children: string }) => {
    const navigate = useNavigate();

    return (
        <CellLink title={children} onClick={() => navigate(`/manage/event/${eventId}/dashboard`)}>
            {children}
        </CellLink>
    );
};
