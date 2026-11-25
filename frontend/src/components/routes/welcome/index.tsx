import {t} from "@lingui/macro";
import {useCallback, useEffect, useRef, useState} from "react";
import {Navigate} from "react-router";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {useGetEvents} from "../../../queries/useGetEvents.ts";
import {AnalyticsEvents, trackEvent} from "../../../utilites/analytics.ts";
import {takeSsoSignupConversion} from "../../../utilites/ssoFlow.ts";
import {clearStoredUtmData} from "../../../utilites/utm.ts";
import {AuthShell, TicketPreview} from "../../layouts/AuthLayout/AuthShell.tsx";
import {VerifyEmailStep} from "./VerifyEmailStep.tsx";
import {FirstEventStep} from "./FirstEventStep.tsx";

const Welcome = () => {
    const {data: user} = useGetMe();
    const organizersQuery = useGetOrganizers();
    const eventsQuery = useGetEvents({pageNumber: 1});
    const [ticketPreview, setTicketPreview] = useState<Partial<TicketPreview>>({});
    const [alreadyHasEvents, setAlreadyHasEvents] = useState(false);
    const hasTrackedSignup = useRef(false);

    const enforcesVerification = !!user?.enforce_email_confirmation_during_registration;
    const requiresVerification = enforcesVerification && !user?.is_email_verified;

    useEffect(() => {
        if (!user || hasTrackedSignup.current) {
            return;
        }
        if (takeSsoSignupConversion() || !user.enforce_email_confirmation_during_registration) {
            hasTrackedSignup.current = true;
            trackEvent(AnalyticsEvents.SIGNUP_COMPLETED);
        }
    }, [user]);

    useEffect(() => {
        if (user && !requiresVerification) {
            clearStoredUtmData();
        }
    }, [user, requiresVerification]);

    useEffect(() => {
        if (eventsQuery.isFetched && (eventsQuery.data?.data.length ?? 0) > 0) {
            setAlreadyHasEvents(true);
        }
    }, [eventsQuery.isFetched]);

    const updateTicketPreview = useCallback((preview: Partial<TicketPreview>) => setTicketPreview(preview), []);

    if (alreadyHasEvents) {
        return <Navigate to="/manage/events"/>;
    }

    const totalSteps = enforcesVerification ? 3 : 2;
    const stepLabel = (step: number) => t`Step ${step} of ${totalSteps}`;
    const isEventStepReady = organizersQuery.isFetched && eventsQuery.isFetched;

    return (
        <AuthShell ticketPreview={requiresVerification ? undefined : ticketPreview}>
            {user && requiresVerification && (
                <VerifyEmailStep user={user} stepLabel={stepLabel(2)}/>
            )}
            {user && !requiresVerification && isEventStepReady && (
                <FirstEventStep
                    user={user}
                    organizer={organizersQuery.data?.data?.[0]}
                    stepLabel={stepLabel(totalSteps)}
                    onPreviewChange={updateTicketPreview}
                />
            )}
        </AuthShell>
    );
};

export default Welcome;
