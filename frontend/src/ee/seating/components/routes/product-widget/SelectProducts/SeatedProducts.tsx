import {ReactNode, useEffect} from "react";
import {useInViewport} from "@mantine/hooks";
import {IdParam, Product} from "../../../../../../types.ts";
import {PublicEventSeatMap} from "../../../../api/seat-map.client.ts";
import {SeatedProductsSection} from "../../../SeatedProductsSection";
import {SeatPicker} from "../../../SeatPicker";
import {SeatChoice} from "../../../SeatPicker/ticketOptions.ts";
import {useSeatSelection} from "../../../SeatPicker/useSeatSelection.ts";

export interface SeatedProductsProps {
    eventId: IdParam;
    occurrenceId: IdParam | undefined;
    currency: string;
    seatMap: PublicEventSeatMap;
    products: Product[];
    choices: SeatChoice[];
    lostSeatUids: string[];
    areaId: string | undefined;
    pickerOpened: boolean;
    isContinuing: boolean;
    disabled: boolean;
    disabledMessage: string | null;
    selectsInline: boolean;
    isHostedInParentModal: boolean;
    extrasCount: number;
    extrasTotal: number;
    addons: ReactNode;
    extras: ReactNode | null;
    onChange: (choices: SeatChoice[]) => void;
    onSelectionBlockedChange: (isBlocked: boolean) => void;
    onAreaChange: (areaId: string) => void;
    onOpenPicker: () => void;
    onClosePicker: () => void;
    onContinue: () => void;
}

export const SeatedProducts = (props: SeatedProductsProps) => {
    const {ref: sectionRef, inViewport} = useInViewport<HTMLDivElement>();
    const selection = useSeatSelection({...props, isActive: props.pickerOpened || (!props.disabled && inViewport)});
    const isSelectionBlocked = selection.orphanLabels.length > 0
        || selection.hasUnaccompaniedCompanions
        || selection.minimumWarnings.length > 0;
    const {onSelectionBlockedChange} = props;
    const canFindBestAvailable = props.occurrenceId !== undefined;

    useEffect(() => {
        onSelectionBlockedChange(isSelectionBlocked);
    }, [isSelectionBlocked, onSelectionBlockedChange]);

    return (
        <>
            <SeatedProductsSection sectionRef={sectionRef} selection={selection} seatMap={props.seatMap} currency={props.currency}
                                   canFindBestAvailable={canFindBestAvailable}
                                   disabled={props.disabled} disabledMessage={props.disabledMessage}
                                   selectsInline={props.selectsInline} isCoveredByPicker={props.pickerOpened}
                                   extrasCount={props.extrasCount} extrasTotal={props.extrasTotal} addons={props.addons}
                                   onAreaChange={props.onAreaChange} onOpenFullScreen={props.onOpenPicker}/>
            <SeatPicker opened={props.pickerOpened} selection={selection} seatMap={props.seatMap} currency={props.currency}
                        canFindBestAvailable={canFindBestAvailable} isContinuing={props.isContinuing}
                        withCloseButton={!props.isHostedInParentModal} extras={props.extras} extrasCount={props.extrasCount} extrasTotal={props.extrasTotal}
                        onAreaChange={props.onAreaChange} onContinue={props.onContinue} onClose={props.onClosePicker}/>
        </>
    );
};
