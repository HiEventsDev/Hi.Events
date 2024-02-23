import {Outlet} from "react-router-dom";
import {LoadingMask} from "../../common/LoadingMask";
import '../../../styles/widget/default.scss';

export const TicketWidget = () => {
    return (
        <div className={'hi-ticket-widget-container'}>
            <LoadingMask/>
            <Outlet/>
        </div>
    );
}