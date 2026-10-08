import {useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {BoxOffice, BoxOfficeWithPin, IdParam} from "../../../types.ts";
import {useResetBoxOfficePin} from "../mutations/useResetBoxOfficePin.ts";
import {BoxOfficePinModal, BoxOfficePinMode} from "../components/modals/BoxOfficePinModal";
import {boxOfficeUrl} from "../components/common/BoxOfficeTable";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError} from "../../../utilites/notifications.tsx";

export const useBoxOfficePin = () => {
    const {eventId} = useParams();
    const resetPinMutation = useResetBoxOfficePin();
    const [pinModal, setPinModal] = useState<{ boxOffice: BoxOfficeWithPin, mode: BoxOfficePinMode } | null>(null);

    const generatePin = (boxOffice: BoxOffice) => {
        const mode: BoxOfficePinMode = boxOffice.has_pin ? 'reset' : 'set';
        const generate = () => resetPinMutation.mutate({eventId, boxOfficeId: boxOffice.id as IdParam}, {
            onSuccess: ({data}) => setPinModal({boxOffice: data, mode}),
            onError: (error: any) => showError(error?.response?.data?.message || error.message),
        });

        if (boxOffice.has_pin) {
            confirmationDialog(t`Reset the PIN? Staff who are signed in will be signed out and need the new PIN.`, generate);
            return;
        }

        generate();
    };

    const openBoxOffice = (boxOffice: BoxOffice) => {
        if (!boxOffice.has_pin) {
            generatePin(boxOffice);
            return;
        }

        window.open(boxOfficeUrl(boxOffice.short_id), '_blank');
    };

    const pinModalElement = pinModal && (
        <BoxOfficePinModal
            onClose={() => setPinModal(null)}
            boxOfficeName={pinModal.boxOffice.name}
            boxOfficeShortId={pinModal.boxOffice.short_id}
            pin={pinModal.boxOffice.pin}
            mode={pinModal.mode}
        />
    );

    return {generatePin, openBoxOffice, pinModalElement};
};
