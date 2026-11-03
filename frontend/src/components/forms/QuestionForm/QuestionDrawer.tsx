import {QuestionFormValues} from "./questionFormValues.ts";
import {KeyboardEvent, ReactNode, useId} from "react";
import {t} from "@lingui/macro";
import {Button, Text} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {modals} from "@mantine/modals";
import {QuestionBelongsToType} from "../../../types.ts";
import {SideDrawer, SideDrawerHeading} from "../../common/SideDrawer";

interface QuestionDrawerProps {
    title: string;
    form: UseFormReturnType<QuestionFormValues>;
    loading?: boolean;
    submitLabel: string;
    submitLoading: boolean;
    onSubmit: (values: QuestionFormValues) => void;
    onClose: () => void;
    children: ReactNode;
}

export const QuestionDrawer = ({
                                   title,
                                   form,
                                   loading,
                                   submitLabel,
                                   submitLoading,
                                   onSubmit,
                                   onClose,
                                   children,
                               }: QuestionDrawerProps) => {
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

    const handleKeyDown = (event: KeyboardEvent) => {
        if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
            event.preventDefault();
            (document.getElementById(formId) as HTMLFormElement | null)?.requestSubmit();
        }
    };

    return (
        <SideDrawer
            opened
            onClose={handleClose}
            loading={loading}
            header={(
                <SideDrawerHeading
                    title={title}
                    subtitle={form.values.belongs_to === QuestionBelongsToType.PRODUCT
                        ? t`Asked for each attendee`
                        : t`Asked once per order`}
                />
            )}
            footer={(
                <>
                    <Button variant="default" onClick={handleClose}>
                        {t`Cancel`}
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={submitLoading || loading}
                        data-testid="question-submit-button"
                    >
                        {submitLoading ? t`Working...` : submitLabel}
                    </Button>
                </>
            )}
        >
            <form id={formId} onSubmit={form.onSubmit(onSubmit)} onKeyDown={handleKeyDown}>
                {children}
            </form>
        </SideDrawer>
    );
};
