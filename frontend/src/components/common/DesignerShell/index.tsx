import {ReactNode, useState} from "react";
import {Button, SegmentedControl, Text, Tooltip, VisuallyHidden} from "@mantine/core";
import {IconDeviceDesktop, IconDeviceMobile} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useUnsavedChangesGuard} from "../../../hooks/useUnsavedChangesGuard.ts";
import classes from "./DesignerShell.module.scss";

type MobileView = 'edit' | 'preview';

export type PreviewDevice = 'desktop' | 'mobile';

interface DesignerSectionProps {
    icon: ReactNode;
    title: string;
    action?: ReactNode;
    children: ReactNode;
}

export const DesignerSection = ({icon, title, action, children}: DesignerSectionProps) => (
    <section className={classes.section}>
        <div className={classes.sectionHeader}>
            <h3 className={classes.sectionTitle}>
                {icon}
                {title}
            </h3>
            {action}
        </div>
        {children}
    </section>
);

interface PreviewDeviceToggleProps {
    value: PreviewDevice;
    onChange: (device: PreviewDevice) => void;
}

export const PreviewDeviceToggle = ({value, onChange}: PreviewDeviceToggleProps) => (
    <SegmentedControl
        size="xs"
        value={value}
        onChange={(device) => onChange(device as PreviewDevice)}
        data={[
            {
                value: 'desktop',
                label: (
                    <Tooltip label={t`Desktop`} withArrow>
                        <span className={classes.deviceIcon}>
                            <IconDeviceDesktop size={16} aria-hidden/>
                            <VisuallyHidden>{t`Desktop`}</VisuallyHidden>
                        </span>
                    </Tooltip>
                ),
            },
            {
                value: 'mobile',
                label: (
                    <Tooltip label={t`Mobile`} withArrow>
                        <span className={classes.deviceIcon}>
                            <IconDeviceMobile size={16} aria-hidden/>
                            <VisuallyHidden>{t`Mobile`}</VisuallyHidden>
                        </span>
                    </Tooltip>
                ),
            },
        ]}
    />
);

interface DesignerLayoutProps {
    title: string;
    footer: ReactNode;
    previewActions?: ReactNode;
    preview: ReactNode;
    children: ReactNode;
}

export const DesignerLayout = ({title, footer, previewActions, preview, children}: DesignerLayoutProps) => {
    const [mobileView, setMobileView] = useState<MobileView>('edit');

    return (
        <div className={classes.designer}>
            <div className={classes.container} data-mobile-view={mobileView}>
                <div className={classes.mobileViewToggle}>
                    <SegmentedControl
                        fullWidth
                        size="sm"
                        value={mobileView}
                        onChange={(value) => setMobileView(value as MobileView)}
                        data={[
                            {value: 'edit', label: t`Edit`},
                            {value: 'preview', label: t`Preview`},
                        ]}
                    />
                </div>

                <div className={classes.panel}>
                    <div className={classes.panelBody}>
                        <h2 className={classes.title}>{title}</h2>
                        {children}
                    </div>

                    <div className={classes.saveBar}>
                        {footer}
                    </div>
                </div>

                <div className={classes.previewPane}>
                    <div className={classes.previewToolbar}>
                        <Text size="xs" fw={500} c="dimmed" tt="uppercase" className={classes.previewLabel}>
                            {t`Preview`}
                        </Text>
                        {previewActions}
                    </div>
                    <div className={classes.previewStage}>
                        {preview}
                    </div>
                </div>
            </div>
        </div>
    );
};

interface DesignerShellProps {
    title: string;
    hasUnsavedChanges: boolean;
    isSaving: boolean;
    onSave: () => void;
    onDiscard: () => void;
    previewActions?: ReactNode;
    preview: ReactNode;
    children: ReactNode;
}

export const DesignerShell = ({
    hasUnsavedChanges,
    isSaving,
    onSave,
    onDiscard,
    ...layoutProps
}: DesignerShellProps) => {
    useUnsavedChangesGuard(hasUnsavedChanges, t`Leave without saving? Your design changes will be lost.`);

    return (
        <DesignerLayout
            {...layoutProps}
            footer={(
                <>
                    <Text size="xs" c={hasUnsavedChanges ? 'orange.7' : 'dimmed'} className={classes.saveStatus}>
                        {hasUnsavedChanges ? t`Unsaved changes` : t`All changes saved`}
                    </Text>
                    {hasUnsavedChanges && (
                        <Button
                            variant="subtle"
                            color="gray"
                            size="sm"
                            onClick={onDiscard}
                            disabled={isSaving}
                            data-testid="designer-discard-button"
                        >
                            {t`Discard`}
                        </Button>
                    )}
                    <Button
                        size="sm"
                        onClick={onSave}
                        loading={isSaving}
                        disabled={!hasUnsavedChanges}
                        data-testid="designer-save-button"
                    >
                        {t`Save Changes`}
                    </Button>
                </>
            )}
        />
    );
};
