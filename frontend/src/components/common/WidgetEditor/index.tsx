import classes from './WidgetEditor.module.scss';
import {SelectTickets} from "../../routes/ticket-widget/SelectTickets";
import {ColorInput, Group, Textarea} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {matches, useForm} from "@mantine/form";
import {useEffect, useState} from "react";
import {CopyButton} from "../CopyButton";
import {useParams} from "react-router-dom";
import {IconInfoCircle} from "@tabler/icons-react";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {Popover} from "../Popover";

export const WidgetEditor = () => {
    const {eventId} = useParams();
    const colorMessage = t`Color must be a valid hex color code. Example: #ffffff`;
    const colorRegex = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/;
    const {data: eventSettings, isFetched: isEventSettingsFetched} = useGetEventSettings(eventId);
    const form = useForm(
        {
            initialValues: {
                background_color: "#ffffff",
                primary_color: "#828282",
                primary_text_color: "#ffffff",
                secondary_color: "#f5f5f5",
                secondary_text_color: "#828282",
            },
            validate: {
                background_color: matches(colorRegex, colorMessage),
                primary_color: matches(colorRegex, colorMessage),
                primary_text_color: matches(colorRegex, colorMessage),
                secondary_color: matches(colorRegex, colorMessage),
                secondary_text_color: matches(colorRegex, colorMessage),
            },
        }
    );
    const [embedCode, setEmbedCode] = useState<string>("");
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const currentLocation = window?.location;
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const embedScript = `<script async src="${currentLocation.protocol}//${currentLocation.host}/widget.js"></script>`;

    useEffect(() => {
        setEmbedCode(
            // eslint-disable-next-line lingui/no-unlocalized-strings
            '<div ' +
            'data-hievents-id="' + eventId + '" ' +
            'data-hievents-primary-color="' + form.values.primary_color + '" ' +
            'data-hievents-primary-text-color="' + form.values.primary_text_color + '" ' +
            'data-hievents-secondary-color="' + form.values.secondary_color + '" ' +
            'data-hievents-secondary-text-color="' + form.values.secondary_text_color + '" ' +
            'data-hievents-background-color="' + form.values.background_color + '" ' +
            'data-hievents-widget-type="widget" ' +
            'data-hievents-widget-version="1.0" ' +
            'data-hievents-locale="en" ' +
            'class="hievents-widget">' +
            '</div>'
        );

        form.validate();
    }, [form.values]);

    useEffect(() => {
        if (eventSettings) {
            form.setValues({
                background_color: eventSettings.homepage_background_color,
                primary_color: eventSettings.homepage_primary_color,
                primary_text_color: eventSettings.homepage_primary_text_color,
                secondary_color: eventSettings.homepage_secondary_color,
                secondary_text_color: eventSettings.homepage_secondary_text_color,
            });
        }
    }, [isEventSettingsFetched]);

    return (
        <div>
            <div className={classes.widgetGrid}>
                <div className={classes.widgetForm}>
                    <form>
                        <h2 className={classes.formHeader}>
                            {t`Widget Settings`}
                        </h2>
                        <h3>
                            <Group justify={'space-between'}>
                                {t`Colors`}

                                <Popover title={t`These colors are not saved in our system.`}>
                                    <IconInfoCircle size={23}/>
                                </Popover>
                            </Group>
                        </h3>
                        <ColorInput
                            label={t`Background Color`}
                            placeholder="#RRGGBB"
                            {...form.getInputProps('background_color')}
                            required
                            style={{marginBottom: 15}}
                        />
                        <ColorInput
                            label={t`Primary Color`}
                            placeholder="#RRGGBB"
                            {...form.getInputProps('primary_color')}
                            required
                            style={{marginBottom: 15}}
                        />

                        <ColorInput
                            label={t`Primary Text Color`}
                            placeholder="#RRGGBB"
                            {...form.getInputProps('primary_text_color')}
                            required
                            style={{marginBottom: 15}}
                        />

                        <ColorInput
                            label={t`Secondary Color`}
                            placeholder="#RRGGBB"
                            {...form.getInputProps('secondary_color')}
                            required
                            style={{marginBottom: 15}}
                        />

                        <ColorInput
                            label={t`Secondary Text Color`}
                            placeholder="#RRGGBB"
                            {...form.getInputProps('secondary_text_color')}
                            required
                            style={{marginBottom: 15}}
                        />
                        <h3>
                            Embedding
                        </h3>
                        <Textarea
                            description={t`Place this in the <head> of your website.`}
                            label={(
                                <Group>
                                    {t`Embed Script`}
                                    <CopyButton value={embedScript}/>
                                </Group>)
                            }
                            rows={3}
                            value={embedScript}
                        />
                        <Textarea
                            description={t`Paste this where you want the widget to appear.`}
                            label={(
                                <Group>
                                    {t`Embed Code`}
                                    <CopyButton value={embedCode}/>
                                </Group>)
                            }
                            rows={6}
                            value={embedCode}
                        />
                    </form>
                </div>
                <div className={classes.previewPane}>
                    <h2 className={classes.previewHeader}>
                        {t`Ticket Widget Preview`}
                    </h2>
                    <section className={classes.stickyContainer}>
                        <div className={classes.browserChrome}>
                            <div className={classes.browserActionButtons}>
                                <div/>
                                <div/>
                                <div/>
                            </div>
                            <div className={classes.browserAddressBar}>
                                <div>
                                    <Trans><span>https://</span>your-website.com</Trans>
                                </div>
                            </div>
                        </div>
                        <div className={classes.websitePlaceholder}>
                            <h1>{t`Your awesome website 🎉`}</h1>
                            <p className={classes.lorem}>
                                {t`Lorem ipsum...`}
                            </p>

                            <div className={classes.widgetWrapper}>
                                <SelectTickets isInPreviewMode colors={{
                                    primary: form.values.primary_color,
                                    primaryText: form.values.primary_text_color,
                                    secondary: form.values.secondary_color,
                                    secondaryText: form.values.secondary_text_color,
                                    background: form.values.background_color,
                                }}/>
                            </div>

                            <p className={classes.lorem}>
                                {t`Nam placerat elementum...`}
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    );
}