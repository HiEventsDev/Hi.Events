import {notifications} from "@mantine/notifications";
import {IconCheck, IconInfoSmall, IconX} from "@tabler/icons-react";
import React, {ReactNode} from "react";

export const showSuccess = (message: ReactNode) => {
    notifications.show({
        message: message,
        color: 'green',
        icon: <IconCheck/>,
        position: 'top-center',
    })
}

export const showInfo = (message: ReactNode) => {
    notifications.show({
        message: message,
        color: 'blue',
        icon: <IconInfoSmall/>,
        position: 'top-center',
    })
}

export const showError = (message: React.ReactNode) => {
    notifications.show({
        message: message,
        color: 'red',
        icon: <IconX/>,
        position: 'top-center',
    })
}
