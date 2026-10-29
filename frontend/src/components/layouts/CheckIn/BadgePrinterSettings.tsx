import {Button, Select, Switch, Text} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Modal, ModalFooter} from "../../common/Modal";
import {BadgeLabelSize, BadgePrinter} from "../../../hooks/useBadgePrinter.ts";
import {BADGE_SIZE_DIMENSIONS, BadgeBackPrinting, getBadgeSizeLabel} from "../../../utilites/badges.ts";
import {BadgeSize} from "../../../types.ts";
import classes from "./CheckIn.module.scss";

interface BadgePrinterSettingsProps {
    printer: BadgePrinter;
    opened: boolean;
    onClose: () => void;
}

export const BadgePrinterSettings = ({printer, opened, onClose}: BadgePrinterSettingsProps) => {
    const designSize = printer.layout ? getBadgeSizeLabel(printer.layout.size) : '';

    return (
        <Modal opened={opened} onClose={onClose} heading={t`Badge printing`} size="md">
            <div className={classes.printerSettings}>
                {!printer.isKiosk && (
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
                <Text size="xs" c="dimmed">
                    {t`For one-tap printing on a laptop, set the label printer as the default printer and open this page in Chrome started with the --kiosk-printing option.`}
                </Text>
            </div>
            <ModalFooter start={(
                <Button variant="subtle" leftSection={<IconPrinter size={16}/>} onClick={printer.printTestBadge}>
                    {t`Print a test badge`}
                </Button>
            )}>
                <Button onClick={onClose}>{t`Done`}</Button>
            </ModalFooter>
        </Modal>
    );
};
