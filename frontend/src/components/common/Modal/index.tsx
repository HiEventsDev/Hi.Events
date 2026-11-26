import {Modal as MantineModal, ModalProps as MantineModalProps} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import React from "react";
import classes from "./Modal.module.scss";
import classNames from "classnames";
import {t} from "@lingui/macro";
import {useReturnFocusOnClose} from "../../../hooks/useReturnFocusOnClose.ts";

interface ModalProps {
    heading?: string | React.ReactNode,
}

export const Modal = (props: MantineModalProps & ModalProps) => {
    const {heading, children, classNames: customClassNames, ...restProps} = props;
    const isSmallScreen = useMediaQuery('(max-width: 575px)', false, {getInitialValueInEffect: false});
    const extraClassNames = typeof customClassNames === 'object' ? customClassNames : {};
    useReturnFocusOnClose(restProps.opened && restProps.returnFocus !== false);

    return (
        <MantineModal
            fullScreen={isSmallScreen}
            radius="lg"
            padding="lg"
            {...restProps}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            size={props.size ?? 'xl'}
            withCloseButton={true}
            closeButtonProps={{'aria-label': t`Close`, ...restProps.closeButtonProps}}
            title={heading}
            closeOnClickOutside={false}
            classNames={{
                title: classes.modalTitle,
                ...extraClassNames,
                content: classNames(classes.content, extraClassNames.content),
                body: classNames(classes.body, extraClassNames.body),
            }}
        >
            {children}
        </MantineModal>
    )
}

interface ModalFooterProps {
    children: React.ReactNode;
    start?: React.ReactNode;
    inline?: boolean;
}

export const ModalFooter = ({children, start, inline = false}: ModalFooterProps) => (
    <div className={classNames(classes.footer, inline && classes.footerInline)}>
        {start && <div className={classes.footerStart}>{start}</div>}
        {children}
    </div>
);
