import {Modal as MantineModal, ModalProps as MantineModalProps} from "@mantine/core";
import React from "react";
import classes from "./Modal.module.scss";
import classNames from "classnames";

interface ModalProps {
    heading?: string | React.ReactNode,
    modalHeader?: 'default' | 'branded',
}

export const Modal = (props: MantineModalProps & ModalProps) => {
    const { modalHeader = 'default', ...restProps } = props;
    return (
        <MantineModal
            {...restProps}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            size={'xl'}
            withCloseButton={true}
            title={props.heading}
            closeOnClickOutside={false}
            classNames={{
                title: classNames(
                    classes.modalTitle,
                    modalHeader === 'branded' && classes.brandedTitle
                ),
                header: classNames(
                    modalHeader === 'branded' && classes.brandedHeader
                ),
                close: classNames(
                    modalHeader === 'branded' && classes.brandedClose
                ),
                ...props.classNames
            }}
        >
            <div style={{padding: '15px', paddingTop: 0}}>
                {props.children}
            </div>
        </MantineModal>
    )
}
