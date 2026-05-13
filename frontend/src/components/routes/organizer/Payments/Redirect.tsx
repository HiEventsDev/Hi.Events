import {Navigate, useLocation, useParams} from "react-router";

const PaymentsRedirect = () => {
    const {organizerId} = useParams();
    const location = useLocation();
    // Preserve query params (Stripe Connect return flows append them) and
    // jump straight to the Payouts section of the organizer settings page.
    const target = `/manage/organizer/${organizerId}/settings${location.search}#payouts`;
    return <Navigate to={target} replace/>;
};

export default PaymentsRedirect;
