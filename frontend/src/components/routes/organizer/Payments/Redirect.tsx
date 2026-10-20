import {Navigate, useLocation, useParams} from "react-router";

const PaymentsRedirect = () => {
    const {organizerId} = useParams();
    const location = useLocation();
    const target = `/manage/organizer/${organizerId}/settings${location.search}#payouts`;
    return <Navigate to={target} replace/>;
};

export default PaymentsRedirect;
