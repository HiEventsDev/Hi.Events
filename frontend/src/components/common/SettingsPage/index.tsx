import {MouseEvent, ReactNode, useEffect, useRef} from "react";
import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {StatusDot} from "../StatusDot";
import classes from "./SettingsPage.module.scss";

interface SettingsPageProps {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    width?: 'narrow' | 'wide';
    children: ReactNode;
}

export const SettingsPage = ({title, description, actions, width = 'narrow', children}: SettingsPageProps) => (
    <div className={classNames(classes.page, {[classes.wide]: width === 'wide'})}>
        <header className={classes.header}>
            <div className={classes.heading}>
                <h1 className={classes.title}>{title}</h1>
                {description && <p className={classes.description}>{description}</p>}
            </div>
            {actions && <div className={classes.actions}>{actions}</div>}
        </header>
        <div className={classes.body}>{children}</div>
    </div>
);

interface SettingsSectionProps {
    id?: string;
    title?: ReactNode;
    tone?: 'default' | 'danger';
    children: ReactNode;
}

export const SettingsSection = ({id, title, tone = 'default', children}: SettingsSectionProps) => (
    <section id={id} className={classes.section}>
        {title && <h2 className={classes.sectionTitle}>{title}</h2>}
        <div className={classNames(classes.card, {[classes.danger]: tone === 'danger'})}>
            {children}
        </div>
    </section>
);

interface SettingsRowProps {
    label: ReactNode;
    description?: ReactNode;
    htmlFor?: string;
    children?: ReactNode;
    below?: ReactNode;
    inlineControl?: boolean;
}

export const SettingsRow = ({label, description, htmlFor, children, below, inlineControl = false}: SettingsRowProps) => (
    <div className={classes.row}>
        <div className={classNames(classes.rowMain, {[classes.textOnly]: !children, [classes.inlineControl]: inlineControl})}>
            <div className={classes.rowText}>
                {htmlFor
                    ? <label htmlFor={htmlFor} className={classes.rowLabel}>{label}</label>
                    : <div className={classes.rowLabel}>{label}</div>}
                {description && <div className={classes.rowDescription}>{description}</div>}
            </div>
            {children && <div className={classes.rowControl}>{children}</div>}
        </div>
        {below && <div className={classes.rowBelow}>{below}</div>}
    </div>
);

interface SettingsFooterProps {
    hint?: ReactNode;
    children?: ReactNode;
}

export const SettingsFooter = ({hint, children}: SettingsFooterProps) => (
    <div className={classes.footer}>
        <div className={classes.footerHint}>{hint}</div>
        {children && <div className={classes.footerActions}>{children}</div>}
    </div>
);

interface SettingsSaveFooterProps {
    isDirty: boolean;
    isSaving: boolean;
    onDiscard: () => void;
    saveTestId?: string;
}

const isBehindModalDialog = (element: HTMLElement) => Array
    .from(document.querySelectorAll('[role="dialog"][aria-modal="true"]'))
    .some(dialog => !dialog.contains(element));

export const SettingsSaveFooter = ({isDirty, isSaving, onDiscard, saveTestId}: SettingsSaveFooterProps) => {
    const saveButton = useRef<HTMLButtonElement>(null);
    const focusBeforeSave = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const target = focusBeforeSave.current;
        if (isSaving || !target) {
            return;
        }

        focusBeforeSave.current = null;
        const focusWasLost = !document.activeElement || document.activeElement === document.body;
        if (focusWasLost && target.isConnected && !isBehindModalDialog(target)) {
            target.focus();
        }
    }, [isSaving]);

    const handleSaveClick = (event: MouseEvent<HTMLButtonElement>) => {
        if (!isDirty) {
            event.preventDefault();
            return;
        }

        const active = document.activeElement;
        focusBeforeSave.current = active instanceof HTMLElement && active !== document.body ? active : null;
    };

    const handleDiscardClick = (event: MouseEvent<HTMLButtonElement>) => {
        const discardHadFocus = document.activeElement === event.currentTarget;
        onDiscard();
        if (discardHadFocus) {
            saveButton.current?.focus();
        }
    };

    return (
        <SettingsFooter hint={isDirty && <StatusDot tone="warning" strong>{t`Unsaved changes`}</StatusDot>}>
            {isDirty && !isSaving && (
                <Button variant="subtle" color="gray" onClick={handleDiscardClick}>
                    {t`Discard`}
                </Button>
            )}
            <Button
                ref={saveButton}
                type="submit"
                data-disabled={!isDirty || undefined}
                aria-disabled={!isDirty || undefined}
                loading={isSaving}
                onClick={handleSaveClick}
                data-testid={saveTestId}
            >
                {t`Save changes`}
            </Button>
        </SettingsFooter>
    );
};
