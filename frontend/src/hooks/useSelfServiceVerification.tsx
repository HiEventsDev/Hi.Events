import {useState} from "react";
import {VerifyEmailModal} from "../components/common/VerifyEmailModal";
import {getSelfServiceToken, SelfServiceLink} from "../utilites/selfServiceSession.ts";

export const useSelfServiceVerification = (link: SelfServiceLink) => {
    const [pendingAction, setPendingAction] = useState<(() => void) | null>(null);

    const requireVerification = (action: () => void) => {
        if (getSelfServiceToken(link)) {
            action();
            return;
        }

        setPendingAction(() => action);
    };

    const startVerification = (action: () => void) => setPendingAction(() => action);

    const verificationModal = pendingAction && (
        <VerifyEmailModal
            link={link}
            onClose={() => setPendingAction(null)}
            onVerified={() => {
                const action = pendingAction;
                setPendingAction(null);
                action();
            }}
        />
    );

    return {requireVerification, startVerification, verificationModal};
};
