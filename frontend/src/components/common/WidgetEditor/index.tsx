import classes from './WidgetEditor.module.scss';
import SelectTickets from "../../routes/ticket-widget/SelectTickets";
import {ColorInput, Group, NumberInput, Switch, Tabs, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {matches, useForm} from "@mantine/form";
import {useEffect, useState} from "react";
import {CopyButton} from "../CopyButton";
import {useParams} from "react-router-dom";
import {IconInfoCircle} from "@tabler/icons-react";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {Popover} from "../Popover";
import {LoadingMask} from '../LoadingMask';
import {Event} from '../../../types.ts';
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {Card} from "../Card";

export const WidgetEditor = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);

    const colorMessage = t`Color must be a valid hex color code. Example: #ffffff`;
    const colorRegex = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/;
    const {data: eventSettings, isFetched: isEventSettingsFetched} = useGetEventSettings(eventId);
    const form = useForm({
        initialValues: {
            background_color: "#ffffff",
            primary_color: "#828282",
            primary_text_color: "#ffffff",
            secondary_color: "#f5f5f5",
            secondary_text_color: "#828282",
            continue_button_text: t`Continue`,
            padding: 20,
            autoResize: true,
        },
        validate: {
            background_color: matches(colorRegex, colorMessage),
            primary_color: matches(colorRegex, colorMessage),
            primary_text_color: matches(colorRegex, colorMessage),
            secondary_color: matches(colorRegex, colorMessage),
            secondary_text_color: matches(colorRegex, colorMessage),
        },
    });

    const [htmlEmbedCode, setHtmlEmbedCode] = useState<string>("");
    const [reactComponentCode, setReactComponentCode] = useState<string>("");
    const [reactUsageCode, setReactUsageCode] = useState<string>("");
    const currentLocation = typeof window !== "undefined" ? window?.location : undefined;
    const embedUrl = `${currentLocation?.protocol}//${currentLocation?.host}/widget.js`;
    const embedScript = `<script async src="${embedUrl}"></script>`;

    useEffect(() => {
        setHtmlEmbedCode(
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
            'data-hievents-padding="' + form.values.padding + 'px" ' +
            'data-hievents-autoresize="' + form.values.autoResize + '" ' +
            'data-hievents-continue-button-text="' + form.values.continue_button_text + '" ' +
            'class="hievents-widget">' +
            '</div>'
        );

        setReactComponentCode(`
import React, { useEffect } from 'react';

const HiEventsWidget = ({
  eventId,
  primaryColor = "${form.values.primary_color}",
  primaryTextColor = "${form.values.primary_text_color}",
  secondaryColor = "${form.values.secondary_color}",
  secondaryTextColor = "${form.values.secondary_text_color}",
  backgroundColor = "${form.values.background_color}",
  widgetType = "widget",
  widgetVersion = "1.0",
  locale = "en"
}) => {
  useEffect(() => {
    const script = document.createElement('script');
    script.src = "${embedUrl}";
    script.async = true;
    document.head.appendChild(script);

    return () => {
      document.head.removeChild(script);
    };
  }, []);

  return (
    <div
      data-hievents-id={eventId}
      data-hievents-primary-color={primaryColor}
      data-hievents-primary-text-color={primaryTextColor}
      data-hievents-secondary-color={secondaryColor}
      data-hievents-secondary-text-color={secondaryTextColor}
      data-hievents-background-color={backgroundColor}
      data-hievents-widget-type={widgetType}
      data-hievents-widget-version={widgetVersion}
      data-hievents-locale={locale}
      className="hievents-widget"
    />
  );
};

export default HiEventsWidget;
        `);

        setReactUsageCode(`
import React from 'react';
import HiEventsWidget from './HiEventsWidget';

const App = () => {
  return (
    <div>
      <h1>My Website</h1>
      <HiEventsWidget 
        eventId="${eventId}"
        primaryColor="${form.values.primary_color}"
        primaryTextColor="${form.values.primary_text_color}"
        secondaryColor="${form.values.secondary_color}"
        secondaryTextColor="${form.values.secondary_text_color}"
        backgroundColor="${form.values.background_color}"
        widgetType="widget"
        widgetVersion="1.0"
        locale="en"
      />
    </div>
  );
};

export default App;
        `);

        form.validate();
    }, [form.values, eventId]);

    useEffect(() => {
        if (eventSettings) {
            form.setValues({
                background_color: eventSettings.homepage_background_color,
                primary_color: eventSettings.homepage_primary_color,
                primary_text_color: eventSettings.homepage_primary_text_color,
                secondary_color: eventSettings.homepage_secondary_color,
                secondary_text_color: eventSettings.homepage_secondary_text_color,
                continue_button_text: eventSettings.continue_button_text,
            });
        }
    }, [isEventSettingsFetched, eventSettings]);

    return (
        <div>
            <div className={classes.widgetGrid}>
                <Card className={classes.widgetForm}>
                    <form>
                        <h2 className={classes.formHeader}>
                            {t`Widget Settings`}
                        </h2>
                        <h3>
                            <Group justify={'space-between'}>
                                {t`Colors`}

                                <Popover
                                    title={t`The styling settings you choose apply only to copied HTML and won't be stored.`}>
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

                        <h3>{t`Appearance`}</h3>

                        <TextInput
                            label={t`Continue Button Text`}
                            placeholder={t`Continue`}
                            {...form.getInputProps('continue_button_text')}
                            style={{marginBottom: 15}}
                        />

                        <NumberInput
                            label={t`Padding`}
                            min={0}
                            max={500}
                            placeholder={t`20`}
                            rightSection={`px`}
                            {...form.getInputProps('padding')}
                            style={{marginBottom: 15}}
                        />

                        <Switch
                            label={t`Auto Resize`}
                            {...form.getInputProps('autoResize', {type: 'checkbox'})}
                            style={{marginBottom: 15, marginTop: 15}}
                            description={t`Automatically resize the widget height based on the content. When disabled, the widget will fill the height of the container.`}
                        />

                        <h3>
                            Embedding
                        </h3>

                        <Tabs defaultValue="html">
                            <Tabs.List>
                                <Tabs.Tab value="html">
                                    HTML
                                </Tabs.Tab>
                                <Tabs.Tab value="react">
                                    React
                                </Tabs.Tab>
                            </Tabs.List>

                            <div style={{marginTop: 15, marginBottom: 15}}>
                                <Tabs.Panel value="html">
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
                                                <CopyButton value={htmlEmbedCode}/>
                                            </Group>)
                                        }
                                        rows={6}
                                        value={htmlEmbedCode}
                                    />
                                </Tabs.Panel>
                            </div>

                            <Tabs.Panel value="react">
                                <Textarea
                                    description={t`Here is the React component you can use to embed the widget in your application.`}
                                    label={(
                                        <Group>
                                            {t`Component Code`}
                                            <CopyButton value={reactComponentCode}/>
                                        </Group>)
                                    }
                                    rows={6}
                                    value={reactComponentCode}
                                />
                                <Textarea
                                    description={t`Here is an example of how you can use the component in your application.`}
                                    label={(
                                        <Group>
                                            {t`Usage Example`}
                                            <CopyButton value={reactUsageCode}/>
                                        </Group>)
                                    }
                                    rows={6}
                                    value={reactUsageCode}
                                />
                            </Tabs.Panel>

                        </Tabs>

                    </form>
                </Card>
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
                                {t`Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam placerat elementum...`}
                            </p>

                            <div className={classes.widgetWrapper}>
                                {!eventQuery.isFetched ?
                                    <LoadingMask/> :
                                    <SelectTickets
                                        event={eventQuery.data as Event}
                                        widgetMode={'preview'}
                                        colors={{
                                            primary: form.values.primary_color,
                                            primaryText: form.values.primary_text_color,
                                            secondary: form.values.secondary_color,
                                            secondaryText: form.values.secondary_text_color,
                                            background: form.values.background_color,
                                        }}
                                        continueButtonText={form.values.continue_button_text}
                                        padding={form.values.padding + 'px'}
                                    />
                                }
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
};