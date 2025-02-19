import {notifications} from "@mantine/notifications";
import {IconCheck, IconInfoSmall, IconX} from "@tabler/icons-react";
import React, {ReactNode} from "react";

export const showSuccess = (message: ReactNode, icon: ReactNode = <IconCheck/>) => {
    notifications.show({
        message: message,
        color: 'green',
        icon: icon,
        position: 'top-center',
    })
}

export const showInfo = (message: ReactNode, icon: ReactNode = <IconInfoSmall/>) => {
    notifications.show({
        message: message,
        color: 'blue',
        icon: icon,
        position: 'top-center',
    })
}

export const showError = (message: React.ReactNode, icon: ReactNode = <IconX/>) => {
    notifications.show({
        message: message,
        color: 'red',
        icon: icon,
        position: 'top-center',
    })
}
