import {Attendee, EventType} from "../../../types.ts";
import {CheckInController} from "../../../hooks/useCheckInController.tsx";
import {CheckInOptionsModal} from "../../common/CheckIn/CheckInOptionsModal";
import {AttendeeDetailSheet} from "./AttendeeDetailSheet.tsx";

interface CheckInModalsProps {
    controller: CheckInController;
    checkInListShortId: string | undefined;
    eventType?: EventType;
    timezone?: string;
}

export const CheckInModals = ({controller, checkInListShortId, eventType, timezone}: CheckInModalsProps) => {
    return (
        <>
            <CheckInOptionsModal
                isOpen={controller.checkInModalOpen}
                attendee={controller.selectedAttendee}
                isPending={controller.isCheckInPending}
                onClose={controller.closeCheckInModal}
                onCheckIn={(action) => controller.selectedAttendee && controller.handleCheckInAction(controller.selectedAttendee, action)}
            />
            <AttendeeDetailSheet
                checkInListShortId={checkInListShortId}
                attendeePublicId={controller.detailAttendeePublicId}
                eventType={eventType}
                timezone={timezone}
                onClose={() => controller.setDetailAttendeePublicId(null)}
                isActionPending={controller.isCheckInPending || controller.isDeletePending}
                onCheckInToggle={(detail) => {
                    const attendee: Attendee = {
                        id: detail.id,
                        product_id: detail.product_id,
                        product_price_id: 0,
                        order_id: detail.order?.id ?? 0,
                        status: detail.status,
                        first_name: detail.first_name,
                        last_name: detail.last_name,
                        email: detail.email,
                        public_id: detail.public_id,
                        short_id: detail.public_id,
                        check_in: detail.check_ins?.[0] ? {
                            id: detail.check_ins[0].id,
                            attendee_id: detail.check_ins[0].attendee_id,
                            check_in_list_id: detail.check_ins[0].check_in_list_id,
                            product_id: detail.product_id,
                            event_id: 0,
                            short_id: detail.check_ins[0].short_id,
                            order_id: detail.check_ins[0].order_id,
                            created_at: detail.check_ins[0].checked_in_at,
                        } : undefined,
                    };
                    controller.handleCheckInToggle(attendee);
                }}
            />
            <audio ref={controller.scanSuccessAudioRef} src="/sounds/scan-success.wav"/>
            <audio ref={controller.scanErrorAudioRef} src="/sounds/scan-error.wav"/>
        </>
    );
};
