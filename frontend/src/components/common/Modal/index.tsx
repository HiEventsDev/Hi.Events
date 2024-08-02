import {Modal as MantineModal, ModalProps as MantineModalProps} from "@mantine/core";
import React from "react";
import classes from "./Modal.module.scss";

interface ModalProps {
    heading?: string | React.ReactNode,
}

export const Modal = (props: MantineModalProps & ModalProps) => {
    return (
        <MantineModal
            {...props}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            size={'xl'}
            withCloseButton={true}
            title={props.heading}
            closeOnClickOutside={false}
            classNames={{
                title: classes.modalTitle,
            }}
        >
            <div style={{padding: '15px', paddingTop: 0}}>
                {props.children}
            </div>
        </MantineModal>
    )
}
