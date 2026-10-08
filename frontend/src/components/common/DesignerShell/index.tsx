import {ReactNode, useState} from "react";
import {Button, SegmentedControl, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {useUnsavedChangesGuard} from "../../../hooks/useUnsavedChangesGuard.ts";
import classes from "./DesignerShell.module.scss";

type MobileView = 'edit' | 'preview';

interface DesignerSectionProps {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}

export const DesignerSection = ({icon, title, children}: DesignerSectionProps) => (
    <section className={classes.section}>
        <h3 className={classes.sectionTitle}>
            {icon}
            {title}
        </h3>
        {children}
    </section>
);

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
    title,
    hasUnsavedChanges,
    isSaving,
    onSave,
    onDiscard,
    previewActions,
    preview,
    children,
}: DesignerShellProps) => {
    const [mobileView, setMobileView] = useState<MobileView>('edit');

    useUnsavedChangesGuard(hasUnsavedChanges, t`Leave without saving? Your design changes will be lost.`);

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
