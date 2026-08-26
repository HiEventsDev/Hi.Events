import React, {useId} from "react";
import {t} from "@lingui/macro";
import {Button, CloseButton, Drawer, Skeleton, Text} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {modals} from "@mantine/modals";
import {Event, Product} from "../../../types.ts";
import {ProductPreview, ProductVisibilityStatusLine} from "./ProductPreview.tsx";
import classes from "./ProductDrawer.module.scss";

interface ProductDrawerProps {
    onClose: () => void;
    title: string;
    event?: Event;
    form: UseFormReturnType<Product>;
    loading?: boolean;
    submitLabel: string;
    submitLoading: boolean;
    submitTestId?: string;
    onSubmit: (values: Product) => void;
    children: React.ReactNode;
}

const FormPlaceholder = () => (
    <div className={classes.placeholder}>
        <Skeleton height={74} radius="md"/>
        {[45, 70, 55, 65].map((width, index) => (
            <Skeleton key={index} height={12} width={`${width}%`} radius="sm"/>
        ))}
    </div>
);

export const ProductDrawer = ({
                                  onClose,
                                  title,
                                  event,
                                  form,
                                  loading,
                                  submitLabel,
                                  submitLoading,
                                  submitTestId,
                                  onSubmit,
                                  children,
                              }: ProductDrawerProps) => {
    const headingId = useId();
    const formId = useId();

    const handleClose = () => {
        if (!form.isDirty()) {
            onClose();
            return;
        }

        modals.openConfirmModal({
            title: t`Discard changes?`,
            children: (
                <Text size="sm">
                    {t`You have unsaved changes. Are you sure you want to discard them?`}
                </Text>
            ),
            labels: {confirm: t`Discard`, cancel: t`Keep editing`},
            confirmProps: {color: 'red'},
            zIndex: 400,
            onConfirm: onClose,
        });
    };

    const handleKeyDown = (keyboardEvent: React.KeyboardEvent) => {
        if ((keyboardEvent.metaKey || keyboardEvent.ctrlKey) && keyboardEvent.key === 'Enter') {
            keyboardEvent.preventDefault();
            (document.getElementById(formId) as HTMLFormElement | null)?.requestSubmit();
        }
    };

    return (
        <Drawer
            opened
            onClose={handleClose}
            position="right"
            size={900}
            withCloseButton={false}
            closeOnClickOutside={false}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            aria-labelledby={headingId}
            classNames={{
                content: classes.content,
                body: classes.body,
            }}
        >
            <div className={classes.header}>
                <div className={classes.headerText} id={headingId}>
                    <h2 className={classes.headerTitle}>{title}</h2>
                    {event?.title && <span className={classes.headerSubtitle}>{event.title}</span>}
                </div>
                <div className={classes.headerActions}>
                    <span className={classes.escHint}>{t`Esc to close`}</span>
                    <CloseButton
                        aria-label={t`Close`}
                        onClick={handleClose}
                        size="lg"
                        radius="xl"
                    />
                </div>
            </div>

            <div className={classes.main} onKeyDown={handleKeyDown}>
                <div className={classes.formColumn} data-testid="product-form-column" data-autofocus tabIndex={-1}>
                    {loading
                        ? <FormPlaceholder/>
                        : (
                            <>
                                <form id={formId} onSubmit={form.onSubmit(onSubmit)}>
                                    {children}
                                </form>
                                <div className={classes.statusBelowForm}>
                                    <ProductVisibilityStatusLine form={form} event={event}/>
                                </div>
                            </>
                        )}
                </div>
                <aside className={classes.previewColumn}>
                    <ProductPreview form={form} event={event}/>
                </aside>
            </div>

            <div className={classes.footer}>
                <span className={classes.footerHint}>
                    {t`Only a name is required — everything else has sensible defaults`}
                </span>
                <div className={classes.footerActions}>
                    <Button variant="default" onClick={handleClose}>
                        {t`Cancel`}
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={submitLoading || loading}
                        data-testid={submitTestId}
                    >
                        {submitLoading ? t`Working...` : submitLabel}
                    </Button>
                </div>
            </div>
        </Drawer>
    );
};
