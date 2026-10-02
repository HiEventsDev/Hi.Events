import {ReactNode, RefCallback} from "react";
import {Button} from "@mantine/core";
import {IconArmchair, IconArrowsMaximize} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {PublicEventSeatMap} from "../../api/seat-map.client.ts";
import {AreaTabs} from "../AreaTabs.tsx";
import {SeatLegend} from "../SeatLegend.tsx";
import {SeatMapRenderer} from "../SeatMapRenderer";
import {SeatMapZoomControls} from "../SeatMapZoomControls";
import {Basket} from "../SeatPicker/Basket.tsx";
import {SeatSheet} from "../SeatPicker/SeatSheet.tsx";
import {SeatPrices} from "../SeatPicker/SeatPrices.tsx";
import {SeatSelection} from "../SeatPicker/useSeatSelection.ts";
import classes from "./SeatedProductsSection.module.scss";

interface SeatedProductsSectionProps {
    sectionRef: RefCallback<HTMLDivElement>;
    selection: SeatSelection;
    seatMap: PublicEventSeatMap;
    currency: string;
    canFindBestAvailable: boolean;
    disabled: boolean;
    disabledMessage: string | null;
    selectsInline: boolean;
    isCoveredByPicker: boolean;
    extrasCount: number;
    extrasTotal: number;
    addons: ReactNode;
    onAreaChange: (areaId: string) => void;
    onOpenFullScreen: () => void;
}

export const SeatedProductsSection = ({
    sectionRef,
    selection,
    seatMap,
    currency,
    canFindBestAvailable,
    disabled,
    disabledMessage,
    selectsInline,
    isCoveredByPicker,
    extrasCount,
    extrasTotal,
    addons,
    onAreaChange,
    onOpenFullScreen,
}: SeatedProductsSectionProps) => {
    const {area, index} = selection;
    const isInteractive = selectsInline && !disabled;
    const hasChoices = selection.lines.length > 0;

    return (
        <div ref={sectionRef} className={classes.section} data-testid="seated-products-section">
            <div className={classes.toolbar}>
                {seatMap.layout.areas.length > 1
                    ? <AreaTabs areas={seatMap.layout.areas} value={area.id} onChange={onAreaChange}/>
                    : <h2 className={classes.heading}>{t`Choose your seats`}</h2>}
                {isInteractive && (
                    <Button size="compact-sm" variant="subtle" className={classes.expandButton}
                            leftSection={<IconArrowsMaximize size={16}/>} onClick={onOpenFullScreen}
                            data-testid="seat-map-expand-button">
                        {t`Bigger map`}
                    </Button>
                )}
            </div>

            <div className={classes.mapWrap}>
                {!isCoveredByPicker && (
                    <SeatMapRenderer area={area} bands={index.bands} seatStates={selection.seatStates} interactive={isInteractive}
                                     zoneRemaining={selection.availability?.zone_remaining} zoneSelected={selection.zoneSelected}
                                     onSeatClick={selection.handleSeatClick} onZoneClick={selection.handleZoneClick}
                                     controls={actions => isInteractive && (
                                         <SeatMapZoomControls {...actions} className={classes.zoomControls}/>
                                     )}/>
                )}

                {selection.hasSeatsForSale && !isCoveredByPicker && (
                    <div className={classes.mapKey}>
                        <SeatLegend bands={[]} layout="inline" showUnavailable showCompanion={selection.hasCompanionSeats}/>
                    </div>
                )}

                <button type="button" className={classes.tapToOpen} disabled={disabled}
                        data-always-on={!isInteractive} onClick={onOpenFullScreen}
                        data-testid="choose-seats-button">
                    {disabledMessage
                        ? <span className={classes.tapMessage}>{disabledMessage}</span>
                        : (
                            <span className={classes.tapLabel}>
                                <IconArmchair size={18}/>
                                {hasChoices ? t`Change seats` : t`Choose seats`}
                            </span>
                        )}
                </button>
            </div>

            {!isCoveredByPicker && selection.hasSeatsForSale && (
                <div className={classes.legend}>
                    <SeatPrices bands={selection.legendBands} options={selection.options} ticketTypes={selection.ticketTypes}
                                bandFree={selection.availability?.band_free} unavailableReasons={selection.unavailableReasons}
                                currency={currency}/>
                </div>
            )}

            {(isInteractive || hasChoices) && (
                <div className={classes.basketWrap}>
                    <Basket lines={selection.lines} total={selection.total} currency={currency}
                            extrasCount={extrasCount} extrasTotal={extrasTotal} afterLines={addons}
                            orphanLabels={selection.orphanLabels} minimumWarnings={selection.minimumWarnings}
                            hasUnaccompaniedCompanions={selection.hasUnaccompaniedCompanions}
                            isSoldOut={selection.isSoldOut} hasSeatsForSale={selection.hasSeatsForSale}
                            maxSeatsPerOrder={selection.maxSeatsPerOrder}
                            lostLabels={selection.lostLabels}
                            bestAvailableOptions={canFindBestAvailable ? selection.bestAvailableOptions : []}
                            isFindingSeats={selection.isFindingSeats}
                            onFindBestAvailable={selection.findBestAvailable}
                            onChangeOption={selection.changeOption}
                            onRemove={selection.removeLine}/>

                    {!isCoveredByPicker && (
                        <SeatSheet target={selection.sheetTarget} currency={currency}
                                   onConfirm={selection.confirmSheet} onClose={() => selection.setSheetTarget(null)}/>
                    )}
                </div>
            )}
        </div>
    );
};
