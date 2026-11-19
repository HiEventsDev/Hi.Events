import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconAlertTriangle} from "@tabler/icons-react";
import shared from "../Audience.module.scss";

export const LoadError = ({message, onRetry}: { message: string, onRetry: () => void }) => (
    <div className={shared.empty} role="alert">
        <IconAlertTriangle size={22} stroke={1.5}/>
        <p>{message}</p>
        <Button variant="default" size="xs" onClick={onRetry}>{t`Try again`}</Button>
    </div>
);
