import {useEffect} from "react";
import {Button, Select, Switch} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Modal, ModalFooter} from "../../common/Modal";
import {BadgeLabelSize, BadgePrinter} from "../../../hooks/useBadgePrinter.ts";
import {BADGE_SIZE_DIMENSIONS, BadgeBackPrinting, getBadgeSizeLabel} from "../../../utilites/badges.ts";
import {BadgeSize} from "../../../types.ts";
import {DeviceSetupGuide} from "./DeviceSetupGuide.tsx";
import classes from "./CheckIn.module.scss";

interface DeviceSetupModalProps {
    printer: BadgePrinter;
    isKiosk: boolean;
    opened: boolean;
    onClose: () => void;
}

export const DeviceSetupModal = ({printer, isKiosk, opened, onClose}: DeviceSetupModalProps) => {
    const designSize = printer.layout ? getBadgeSizeLabel(printer.layout.size) : '';
    const {prepareTestBadge} = printer;

    useEffect(() => {
        if (opened && printer.isEnabled) {
            prepareTestBadge();
        }
    }, [opened, printer.isEnabled, prepareTestBadge]);

    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={isKiosk ? t`Set up this kiosk` : t`Badge printing`}
            size="md"
        >
            {printer.isEnabled && (
                <div className={classes.printerSettings}>
                    {!printer.printsFromATap && !isKiosk && (
                        <Switch
                            size="md"
                            label={t`Print a badge when someone checks in`}
                            description={t`Saved on this device only`}
                            checked={printer.settings.autoPrint}
                            onChange={(event) => printer.setAutoPrint(event.currentTarget.checked)}
                            data-testid="check-in-auto-print-switch"
                        />
                    )}
                    <Select
                        label={t`Label size on this device`}
                        description={t`Use a different size here if this printer takes labels rather than badge inserts`}
                        value={printer.settings.labelSize}
                        onChange={(value) => value && printer.setLabelSize(value as BadgeLabelSize)}
                        allowDeselect={false}
                        data={[
                            {value: 'DESIGN', label: t`Same as the badge design (${designSize})`},
                            ...(Object.keys(BADGE_SIZE_DIMENSIONS) as BadgeSize[]).map((size) => ({
                                value: size,
                                label: getBadgeSizeLabel(size),
                            })),
                        ]}
                    />
                    <Switch
                        size="md"
                        label={t`This printer only prints black`}
                        description={t`For thermal label printers such as Brother QL and Zebra. Colours print as solid black so ribbons and names stay sharp.`}
                        checked={printer.settings.blackInkOnly}
                        onChange={(event) => printer.setBlackInkOnly(event.currentTarget.checked)}
                        data-testid="check-in-black-ink-switch"
                    />
                    {printer.hasBack && (
                        <Select
                            label={t`Back of the badge`}
                            description={t`Fold-over prints the front and back side by side on one page, to fold in half. It works on any printer.`}
                            value={printer.backPrinting}
                            onChange={(value) => value && printer.setBackPrinting(value as BadgeBackPrinting)}
                            allowDeselect={false}
                            data={[
                                {value: 'NONE', label: t`Front only`},
                                {value: 'DUPLEX', label: t`Two-sided printer`},
                                {value: 'FOLD_OVER', label: t`Fold-over`},
                            ]}
                            data-testid="check-in-back-printing-select"
                        />
                    )}
                </div>
            )}
            <DeviceSetupGuide printsBadges={printer.isEnabled} isKiosk={isKiosk}/>
            <ModalFooter start={printer.isEnabled && (
                <Button
                    variant="subtle"
                    leftSection={<IconPrinter size={16}/>}
                    loading={printer.isTestBadgePreparing}
                    onClick={printer.printTestBadge}
                    data-testid="check-in-test-print-button"
                >
                    {t`Print a test badge`}
                </Button>
            )}>
                <Button onClick={onClose}>{t`Done`}</Button>
            </ModalFooter>
        </Modal>
    );
};
