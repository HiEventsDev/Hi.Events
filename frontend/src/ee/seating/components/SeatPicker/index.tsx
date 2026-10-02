import {ReactNode, useEffect, useState} from "react";
import {Button, Modal, SegmentedControl, Text} from "@mantine/core";
import {IconArrowLeft, IconList, IconMap2} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {PublicEventSeatMap} from "../../api/seat-map.client.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {AreaTabs} from "../AreaTabs.tsx";
import {SeatLegend} from "../SeatLegend.tsx";
import {SeatMapRenderer} from "../SeatMapRenderer";
import {SeatMapZoomControls} from "../SeatMapZoomControls";
import {Basket} from "./Basket.tsx";
import {SeatPrices} from "./SeatPrices.tsx";
import {SeatListView} from "./SeatListView.tsx";
import {SeatSheet} from "./SeatSheet.tsx";
import {SeatSelection} from "./useSeatSelection.ts";
import classes from "./SeatPicker.module.scss";

const PICKER_Z_INDEX = 10000;

interface SeatPickerProps {
    opened: boolean;
    selection: SeatSelection;
    seatMap: PublicEventSeatMap;
    currency: string;
    canFindBestAvailable: boolean;
    isContinuing: boolean;
    withCloseButton: boolean;
    extras: ReactNode | null;
    extrasCount: number;
    extrasTotal: number;
    onAreaChange: (areaId: string) => void;
    onContinue: () => void;
    onClose: () => void;
}

export const SeatPicker = (props: SeatPickerProps) => {
    const {seatMap, selection} = props;
    const [viewMode, setViewMode] = useState<'map' | 'list'>('map');
    const [step, setStep] = useState<'seats' | 'extras'>('seats');
    const {area, index} = selection;

    useEffect(() => {
        if (!props.opened) {
            setStep('seats');
        }
    }, [props.opened]);

    const isExtrasStep = step === 'extras' && props.extras !== null;

    return (
        <Modal opened={props.opened} onClose={props.onClose} fullScreen padding={0}
               withCloseButton={props.withCloseButton}
               title={isExtrasStep ? t`Anything else?` : t`Choose your seats`}
               zIndex={PICKER_Z_INDEX}
               styles={{header: {padding: '12px 16px', borderBottom: '1px solid var(--mantine-color-gray-2)'}, body: {padding: 0}}}>
            {isExtrasStep ? (
                <div className={classes.extrasLayout} data-testid="seat-picker-extras-step">
                    <div className={classes.extrasBody}>
                        {props.extras}
                    </div>
                    <div className={classes.extrasFooter}>
                        <Button variant="subtle" leftSection={<IconArrowLeft size={16}/>} onClick={() => setStep('seats')}
                                data-testid="seat-picker-back-button">
                            {t`Back to seats`}
                        </Button>
                        <div className={classes.extrasTotal}>
                            <Text size="xs" c="dimmed">{t`Order total`}</Text>
                            <Text fw={700} data-testid="seat-picker-order-total">
                                {formatCurrency(selection.total + props.extrasTotal, props.currency)}
                            </Text>
                        </div>
                        <Button size="md" loading={props.isContinuing} onClick={props.onContinue}
                                className={classes.extrasCheckout} data-testid="seat-picker-checkout-button">
                            {t`Continue to checkout`}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className={classes.layout}>
                    <div className={classes.stage}>
                        <div className={classes.toolbar}>
                            <AreaTabs areas={seatMap.layout.areas} value={area.id} onChange={props.onAreaChange}/>
                            <SegmentedControl size="xs" value={viewMode} data-testid="seat-picker-view-toggle"
                                              onChange={value => setViewMode(value as 'map' | 'list')}
                                              data={[
                                                  {value: 'map', label: <IconMap2 size={16} aria-label={t`Map view`}/>},
                                                  {value: 'list', label: <IconList size={16} aria-label={t`List view`}/>},
                                              ]}/>
                        </div>

                        {viewMode === 'map' ? (
                            <div className={classes.map} data-testid="seat-picker-map">
                                <SeatMapRenderer area={area} bands={index.bands} seatStates={selection.seatStates} interactive
                                                 wheelZoom="always"
                                                 zoneRemaining={selection.availability?.zone_remaining} zoneSelected={selection.zoneSelected}
                                                 onSeatClick={selection.handleSeatClick} onZoneClick={selection.handleZoneClick}
                                                 controls={actions => (
                                                     <SeatMapZoomControls {...actions} large className={classes.zoomControls}/>
                                                 )}/>
                            </div>
                        ) : (
                            <SeatListView area={area} bands={index.bands} seatStates={selection.seatStates}
                                          zoneRemaining={selection.availability?.zone_remaining ?? {}}
                                          onSeatClick={selection.handleSeatClick} onZoneClick={selection.handleZoneClick}/>
                        )}

                        <div className={classes.legend}>
                            <SeatLegend bands={selection.legendBands} layout="inline" showUnavailable={selection.hasSeatsForSale}
                                        showCompanion={selection.hasCompanionSeats}/>
                        </div>
                    </div>

                    <Basket lines={selection.lines} total={selection.total} currency={props.currency}
                            extrasCount={props.extrasCount} extrasTotal={props.extrasTotal}
                            orphanLabels={selection.orphanLabels} minimumWarnings={selection.minimumWarnings}
                            hasUnaccompaniedCompanions={selection.hasUnaccompaniedCompanions}
                            isSoldOut={selection.isSoldOut} hasSeatsForSale={selection.hasSeatsForSale}
                            maxSeatsPerOrder={selection.maxSeatsPerOrder}
                            lostLabels={selection.lostLabels}
                            bestAvailableOptions={props.canFindBestAvailable ? selection.bestAvailableOptions : []}
                            isFindingSeats={selection.isFindingSeats} isContinuing={props.isContinuing}
                            continueLabel={props.extras !== null ? t`Next` : undefined}
                            dropdownZIndex={PICKER_Z_INDEX + 1}
                            emptyState={(
                                <SeatPrices bands={selection.legendBands} options={selection.options}
                                            ticketTypes={selection.ticketTypes} bandFree={selection.availability?.band_free}
                                            unavailableReasons={selection.unavailableReasons} currency={props.currency}/>
                            )}
                            onFindBestAvailable={selection.findBestAvailable}
                            onChangeOption={selection.changeOption}
                            onRemove={selection.removeLine}
                            onContinue={props.extras !== null ? () => setStep('extras') : props.onContinue}/>
                </div>
            )}

            <SeatSheet target={selection.sheetTarget} currency={props.currency} zIndex={PICKER_Z_INDEX + 1}
                       onConfirm={selection.confirmSheet} onClose={() => selection.setSheetTarget(null)}/>
        </Modal>
    );
};
