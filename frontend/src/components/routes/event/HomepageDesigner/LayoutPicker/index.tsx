import {Radio, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {EventLayout, HomepageThemeSettings} from "../../../../../types.ts";
import {EVENT_LAYOUTS, resolveEventLayout} from "../../../../../constants/eventLayouts.ts";
import {LayoutPreview} from "./LayoutPreview.tsx";
import classes from "./LayoutPicker.module.scss";

const layoutCopy = (layout: EventLayout): { name: string, summary: string, hint: string } => {
    switch (layout) {
        case 'MARQUEE':
            return {
                name: t`Marquee`,
                summary: t`Wide cover, tickets alongside`,
                hint: t`On large screens tickets stay beside your content as people scroll. Suits conferences and long lineups.`,
            };
        case 'FLYER':
            return {
                name: t`Flyer`,
                summary: t`Your artwork as a poster`,
                hint: t`Your cover sits beside the details like a poster. Best with square or portrait artwork.`,
            };
        case 'STORY':
            return {
                name: t`Story`,
                summary: t`One column, top to bottom`,
                hint: t`A focused reading column. Tickets appear wherever you place them in Sections.`,
            };
        case 'INVITE':
            return {
                name: t`Invite`,
                summary: t`A compact, centred card`,
                hint: t`A narrow card with the details centred. Suits smaller and private events.`,
            };
    }
};

interface LayoutPickerProps {
    theme: HomepageThemeSettings;
    coverIsLandscape: boolean;
    disabled?: boolean;
    onChange: (layout: EventLayout) => void;
}

export const LayoutPicker = ({theme, coverIsLandscape, disabled, onChange}: LayoutPickerProps) => {
    const selected = resolveEventLayout(theme.layout).value;

    return (
        <Radio.Group value={selected} onChange={value => onChange(value as EventLayout)} label={t`Page layout`} labelProps={{className: classes.hiddenLabel}}>
            <div className={classes.grid}>
                {EVENT_LAYOUTS.map(({value}) => {
                    const copy = layoutCopy(value);
                    return (
                        <Radio.Card
                            key={value}
                            value={value}
                            disabled={disabled}
                            className={classes.card}
                            data-testid={`homepage-layout-${value.toLowerCase()}`}
                        >
                            <span className={classes.preview}>
                                <LayoutPreview layout={value} theme={theme}/>
                            </span>
                            <span className={classes.label}>
                                <Radio.Indicator size="xs"/>
                                <span className={classes.name}>{copy.name}</span>
                            </span>
                            <span className={classes.summary}>{copy.summary}</span>
                        </Radio.Card>
                    );
                })}
            </div>
            {selected === 'FLYER' && coverIsLandscape
                ? <Text size="xs" c="orange.8" mt="sm">{t`Your cover image is landscape, so it will look small here. Flyer works best with square or portrait artwork.`}</Text>
                : <Text size="xs" c="dimmed" mt="sm">{layoutCopy(selected).hint}</Text>}
        </Radio.Group>
    );
};
