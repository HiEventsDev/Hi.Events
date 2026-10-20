import classes from './WidgetEditor.module.scss';
import {ActionIcon, Button, CopyButton as MantineCopyButton, Menu, Select, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {useMemo, useState} from "react";
import {IconAdjustmentsHorizontal, IconCheck, IconCopy, IconDots, IconPencil, IconTrash} from "@tabler/icons-react";
import {DesignerLayout, DesignerSection, PreviewDevice, PreviewDeviceToggle} from "../DesignerShell";
import {Event, EventOccurrence, HomepageSection, HomepageThemeSettings} from '../../../types.ts';
import {WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";
import {resolveWidgetColors, WidgetColors} from "../../../utilites/widgetTheme.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {firstApiError} from "../../../utilites/apiErrors.ts";
import {confirmationDialogAsync} from "../../../utilites/confirmationDialog.tsx";
import {useAccess} from "../../../hooks/useAccess.ts";
import {useUnsavedChangesGuard} from "../../../hooks/useUnsavedChangesGuard.ts";
import {WidgetOwner} from "../../../api/widget.client.ts";
import {useGetWidgets} from "../../../queries/useGetWidgets.ts";
import {useUpsertWidget} from "../../../mutations/useUpsertWidget.ts";
import {useDeleteWidget} from "../../../mutations/useDeleteWidget.ts";
import {settingsForType, widgetAttributes, widgetScriptUrl, WidgetTarget} from "./embedCode.ts";
import {InstallPlatform, installCode} from "./installGuides.ts";
import {COLOR_KEYS, COLOR_REGEX, defaultSettings, savedSettings, SelectableIds, settingsForNewType} from "./editorSettings.ts";
import {WidgetPreview} from "./WidgetPreview.tsx";
import {TypePicker} from "./TypePicker.tsx";
import {WidgetStats} from "./WidgetStats.tsx";
import {NameModal} from "./NameModal.tsx";
import {CustomiseGroups} from "./CustomiseGroups.tsx";
import {LayoutPicker, LineupNotice} from "./ContentFields.tsx";
import {InstallPanel} from "./InstallPanel.tsx";
import {useLineupSectionAvailability} from "./sectionAvailability.ts";

const UNSAVED = 'unsaved';

const withButtonThemeColors = (type: WidgetType, settings: WidgetSettings, themeColors: WidgetColors): WidgetSettings =>
    type === 'BUTTON' && settings.theme === 'event'
        ? {
            ...settings,
            secondary_color: settings.secondary_color ?? themeColors.secondary,
            secondary_text_color: settings.secondary_text_color ?? themeColors.secondaryText,
        }
        : settings;

export interface WidgetEditorProps {
    target: WidgetTarget;
    theme?: Partial<HomepageThemeSettings> | null;
    event?: Event;
    occurrences?: EventOccurrence[];
    homepageSections?: HomepageSection[] | null;
    homepageDesignerPath: string;
    lineupPath?: string;
}

export const WidgetEditor = ({target, theme, event, occurrences, homepageSections, homepageDesignerPath, lineupPath}: WidgetEditorProps) => {
    const isOrganizer = 'organizerId' in target;
    const owner: WidgetOwner = 'organizerId' in target ? {organizerId: target.organizerId} : {eventId: target.eventId};
    const {isReadOnly} = useAccess();
    const widgetsQuery = useGetWidgets(owner);
    const upsertMutation = useUpsertWidget(owner);
    const deleteMutation = useDeleteWidget(owner);

    const [type, setType] = useState<WidgetType>(isOrganizer ? 'EVENTS' : 'TICKETS');
    const [settings, setSettings] = useState<WidgetSettings>(() => defaultSettings(isOrganizer ? 'EVENTS' : 'TICKETS'));
    const [activeId, setActiveId] = useState<number | null>(null);
    const [platform, setPlatform] = useState<InstallPlatform>('html');
    const [device, setDevice] = useState<PreviewDevice>('desktop');
    const [nameModal, setNameModal] = useState<'create' | 'rename' | null>(null);
    const [codeOutdated, setCodeOutdated] = useState(false);

    const widgets = widgetsQuery.data ?? [];
    const activeWidget = widgets.find(widget => widget.id === activeId) ?? null;
    const cleanSettings = useMemo(() => settingsForType(type, settings), [type, settings]);
    const isDirty = !!activeWidget && JSON.stringify([type, cleanSettings])
        !== JSON.stringify([activeWidget.type, settingsForType(activeWidget.type, activeWidget.settings)]);
    const themeColors = useMemo(() => resolveWidgetColors({theme: 'event'}, theme ?? {}), [theme]);
    const invalidColor = COLOR_KEYS.some(key => cleanSettings[key] && !COLOR_REGEX.test(String(cleanSettings[key])));
    const locked = isReadOnly && !!activeWidget;
    const lineupAvailability = useLineupSectionAvailability(event?.id, homepageSections);
    const selectable: SelectableIds = useMemo(() => ({
        products: event?.product_categories?.flatMap(category => (category.products ?? []).map(product => Number(product.id))),
        categories: event?.product_categories?.map(category => Number(category.id)),
        occurrences: occurrences?.map(occurrence => Number(occurrence.id)),
    }), [event, occurrences]);

    useUnsavedChangesGuard(isDirty, t`Leave without saving? Your widget changes will be lost.`);

    const update = (patch: Partial<WidgetSettings>) => setSettings(previous => ({...previous, ...patch}));

    const changeType = (next: WidgetType) => {
        setType(next);
        setSettings(previous => settingsForNewType(next, previous));
    };

    const selectWidget = async (value: string | null) => {
        if (isDirty && !await confirmationDialogAsync(t`Discard your unsaved changes to this widget?`, {confirm: t`Discard`})) {
            return;
        }
        setCodeOutdated(false);
        if (!value || value === UNSAVED) {
            setActiveId(null);
            return;
        }
        const widget = widgets.find(candidate => String(candidate.id) === value);
        if (widget) {
            setActiveId(widget.id);
            setType(widget.type);
            setSettings(savedSettings(widget, selectable));
        }
    };


    const save = (name: string, widgetId?: number) => {
        const changesEmbedCode = embedCodeChanged;
        upsertMutation.mutate(
            {widgetId, widget: {name, type, settings: cleanSettings}},
            {
                onSuccess: ({data}) => {
                    setActiveId(data.id);
                    setNameModal(null);
                    setCodeOutdated(!widgetId || changesEmbedCode);
                    showSuccess(widgetId ? t`Widget saved` : t`Widget created`);
                },
                onError: (error) => showError(firstApiError(error, t`Unable to save the widget. Please check your settings and try again.`)),
            },
        );
    };

    const rename = (name: string) => {
        if (!activeWidget) {
            return;
        }
        upsertMutation.mutate(
            {
                widgetId: activeWidget.id,
                widget: {name, type: activeWidget.type, settings: settingsForType(activeWidget.type, activeWidget.settings)},
            },
            {
                onSuccess: () => setNameModal(null),
                onError: (error) => showError(firstApiError(error, t`Unable to rename the widget`)),
            },
        );
    };

    const removeWidget = async () => {
        if (!activeWidget) {
            return;
        }
        const widgetName = activeWidget.name;
        const confirmed = await confirmationDialogAsync(
            t`Delete "${widgetName}"? Pages that embed it will fall back to the settings in their embed code.`,
            {confirm: t`Delete`},
        );
        if (!confirmed) {
            return;
        }
        deleteMutation.mutate(activeWidget.id, {
            onSuccess: () => {
                setActiveId(null);
                setCodeOutdated(false);
                showSuccess(t`Widget deleted`);
            },
            onError: () => showError(t`Unable to delete the widget`),
        });
    };

    const codeSettings = useMemo(() => withButtonThemeColors(type, cleanSettings, themeColors), [type, cleanSettings, themeColors]);
    const attributes = widgetAttributes(target, type, codeSettings, activeWidget?.short_id);
    const embedCodeChanged = !!activeWidget && JSON.stringify(attributes) !== JSON.stringify(widgetAttributes(
        target,
        activeWidget.type,
        withButtonThemeColors(activeWidget.type, settingsForType(activeWidget.type, activeWidget.settings), themeColors),
        activeWidget.short_id,
    ));
    const scriptUrl = widgetScriptUrl();
    const fullCode = installCode(platform, attributes, scriptUrl);
    const copyBlocked = invalidColor || isDirty;

    const footerNote = (() => {
        if (!activeWidget) {
            return null;
        }
        if (isDirty) {
            return embedCodeChanged ? t`Save, then copy the new code` : t`Unsaved changes`;
        }
        return codeOutdated ? t`Copy the new code to your site` : t`Saved`;
    })();

    return (
        <DesignerLayout
            title={isOrganizer ? t`Events Widget` : t`Embed Widget`}
            previewActions={<PreviewDeviceToggle value={device} onChange={setDevice}/>}
            preview={<WidgetPreview target={target} type={type} settings={codeSettings} device={device}/>}
            footer={(
                <>
                    <Text size="xs" c={isDirty || codeOutdated ? 'orange.7' : 'dimmed'} className={classes.footerNote}>{footerNote}</Text>
                    {!isReadOnly && (!activeWidget || isDirty) && (
                        <Button
                            size="sm"
                            variant={activeWidget ? 'filled' : 'default'}
                            loading={upsertMutation.isPending}
                            disabled={invalidColor}
                            onClick={() => activeWidget ? save(activeWidget.name, activeWidget.id) : setNameModal('create')}
                            data-testid="widget-save-button"
                        >
                            {activeWidget ? t`Save changes` : t`Save widget`}
                        </Button>
                    )}
                    {!(activeWidget && isDirty) && (
                        <MantineCopyButton value={fullCode} timeout={2000}>
                            {({copied, copy}) => (
                                <Button
                                    size="sm"
                                    color={copied ? 'teal' : undefined}
                                    leftSection={copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                                    onClick={() => {
                                        copy();
                                        setCodeOutdated(false);
                                    }}
                                    disabled={invalidColor}
                                    data-testid="widget-copy-code-button"
                                >
                                    {copied ? t`Copied` : t`Copy code`}
                                </Button>
                            )}
                        </MantineCopyButton>
                    )}
                </>
            )}
        >
            <Text size="sm" c="dimmed" className={classes.intro}>
                {isOrganizer
                    ? t`List your upcoming events on your own website. Visitors can buy tickets without leaving your page.`
                    : t`Sell tickets on your own website. Pick a widget, then paste its code into your site.`}
            </Text>

            {widgets.length > 0 && (
                <div className={classes.switcher}>
                    <Select
                        size="sm"
                        label={t`Widget`}
                        value={activeWidget ? String(activeWidget.id) : UNSAVED}
                        onChange={selectWidget}
                        allowDeselect={false}
                        className={classes.switcherSelect}
                        data={[
                            {value: UNSAVED, label: t`New widget`},
                            {group: t`Saved widgets`, items: widgets.map(widget => ({value: String(widget.id), label: widget.name}))},
                        ]}
                        data-testid="widget-switcher"
                    />
                    {activeWidget && !isReadOnly && (
                        <Menu position="bottom-end" withinPortal>
                            <Menu.Target>
                                <ActionIcon variant="default" size={36} aria-label={t`Widget options`} data-testid="widget-options-button">
                                    <IconDots size={18}/>
                                </ActionIcon>
                            </Menu.Target>
                            <Menu.Dropdown>
                                <Menu.Item leftSection={<IconPencil size={16}/>} onClick={() => setNameModal('rename')}>
                                    {t`Rename`}
                                </Menu.Item>
                                <Menu.Item color="red" leftSection={<IconTrash size={16}/>} onClick={removeWidget} data-testid="widget-delete-menu-item">
                                    {t`Delete`}
                                </Menu.Item>
                            </Menu.Dropdown>
                        </Menu>
                    )}
                </div>
            )}
            {activeWidget && <WidgetStats widget={activeWidget}/>}

            {isOrganizer
                ? <LayoutPicker settings={settings} update={update} disabled={locked}/>
                : <TypePicker value={type} onChange={changeType} disabled={locked}/>}
            <LineupNotice
                type={type}
                availability={lineupAvailability}
                homepageDesignerPath={homepageDesignerPath}
                lineupPath={lineupPath}
            />

            <DesignerSection icon={<IconAdjustmentsHorizontal/>} title={t`Customise`}>
                <CustomiseGroups
                    type={type}
                    settings={settings}
                    update={update}
                    isOrganizer={isOrganizer}
                    event={event}
                    occurrences={occurrences}
                    theme={theme}
                    themeColors={themeColors}
                    homepageDesignerPath={homepageDesignerPath}
                    lineupAvailability={lineupAvailability}
                    locked={locked}
                />
            </DesignerSection>

            <InstallPanel
                type={type}
                attributes={attributes}
                scriptUrl={scriptUrl}
                platform={platform}
                onPlatformChange={setPlatform}
                copyBlocked={copyBlocked}
            />

            <NameModal
                opened={nameModal !== null}
                initialName={nameModal === 'rename' ? activeWidget?.name ?? '' : ''}
                title={nameModal === 'rename' ? t`Rename widget` : t`Save widget`}
                submitLabel={nameModal === 'rename' ? t`Rename` : t`Save widget`}
                loading={upsertMutation.isPending}
                onClose={() => setNameModal(null)}
                onSubmit={(name) => nameModal === 'rename' ? rename(name) : save(name)}
            />
        </DesignerLayout>
    );
};
