import {ActionIcon, Button, TextInput, Tooltip} from "@mantine/core";
import {IconPlus, IconTrash} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {SeatMapBand} from "../lib/types.ts";
import classes from "./SeatMapDesigner.module.scss";

interface BandsPanelProps {
    bands: SeatMapBand[];
    usage: Record<string, number>;
    activeBand: string;
    onSelect: (key: string) => void;
    onPreview: (key: string, patch: Partial<Omit<SeatMapBand, 'key'>>) => void;
    onCommit: () => void;
    onAdd: () => void;
    onRemove: (key: string) => void;
}

export const BandsPanel = ({bands, usage, activeBand, onSelect, onPreview, onCommit, onAdd, onRemove}: BandsPanelProps) => (
    <section className={classes.panelSection} onBlur={onCommit}>
        <h3 className={classes.panelHeading}>{t`Price bands`}</h3>
        <p className={classes.panelHint}>
            {t`Bands group seats that share a price. You link tickets to each band on the event. New items use the highlighted band.`}
        </p>
        {bands.map(band => {
            const isUsed = usage[band.key] !== undefined;
            return (
                <div key={band.key} className={classes.bandRow} data-active={band.key === activeBand}
                     onClick={() => onSelect(band.key)}>
                    <input type="color" className={classes.bandColor} aria-label={t`Colour for ${band.name}`}
                           value={band.color} onChange={event => onPreview(band.key, {color: event.currentTarget.value})}/>
                    <TextInput size="xs" variant="unstyled" className={classes.bandName} aria-label={t`Band name`}
                               maxLength={50} value={band.name} error={band.name.trim() === ''}
                               onChange={event => onPreview(band.key, {name: event.currentTarget.value})}/>
                    <span className={classes.bandCount}>{usage[band.key] ?? 0}</span>
                    <Tooltip label={isUsed ? t`Move its seats to another band first` : t`Delete band`}>
                        <ActionIcon variant="subtle" color="red" size="sm" aria-label={t`Delete ${band.name}`}
                                    disabled={isUsed || bands.length === 1}
                                    onClick={event => {
                                        event.stopPropagation();
                                        onRemove(band.key);
                                    }}>
                            <IconTrash size={14}/>
                        </ActionIcon>
                    </Tooltip>
                </div>
            );
        })}
        <Button size="xs" variant="default" leftSection={<IconPlus size={14}/>} onClick={onAdd}
                data-testid="seat-map-designer-add-band-button">
            {t`Add band`}
        </Button>
    </section>
);
