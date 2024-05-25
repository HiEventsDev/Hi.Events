import {Modal as MantineModal, ModalProps as MantineModalProps, Title} from "@mantine/core";
import React from "react";

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
            withCloseButton={false}
        >
            {props.heading && (
                <MantineModal.Header>
                    <MantineModal.Title>
                        <Title order={2}>{props.heading}</Title>
                    </MantineModal.Title>
                    {props.withCloseButton && <MantineModal.CloseButton/>}
                </MantineModal.Header>
            )}

            <div style={{padding: '15px', paddingTop: 0}}>
                {props.children}
            </div>

        </MantineModal>
    )
}