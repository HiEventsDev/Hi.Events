import {Link} from "react-router";
import {Trans} from "@lingui/macro";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {IdParam} from "../../../../types.ts";

interface SendGateReasonProps {
    organizerId: IdParam | undefined;
    reason: string | null | undefined;
    code: string | null | undefined;
    params: Record<string, string | number> | null | undefined;
}

export const SendGateReason = ({organizerId, reason, code, params}: SendGateReasonProps) => {
    const {can} = useAccess();

    if (code !== 'ACCOUNT_NOT_APPROVED' || !organizerId || !can('MANAGE_PAYMENTS')) {
        return <>{reason}</>;
    }

    const payoutSettings = `/manage/organizer/${organizerId}/settings/payouts`;
    const email = String(params?.email ?? '');

    return email
        ? (
            <Trans>
                To protect people from spam, campaigns are switched on once your account is verified. <Link to={payoutSettings}>Connect Stripe</Link> or contact us at <a href={`mailto:${email}`}>{email}</a>.
            </Trans>
        )
        : (
            <Trans>
                To protect people from spam, campaigns are switched on once your account is verified. <Link to={payoutSettings}>Connect Stripe</Link> or contact us to get yours enabled.
            </Trans>
        );
};
