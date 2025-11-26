import {ActionIcon, Button, Text} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconShoppingCartDown, IconShoppingCartUp} from "@tabler/icons-react";
import classes from "./CheckoutFooter.module.scss";
import {Event, Order} from "../../../../types.ts";
import {CheckoutSidebar} from "../CheckoutSidebar";
import {ReactNode, useState} from "react";
import classNames from "classnames";
import {getConfig} from "../../../../utilites/config.ts";
import {isHiEvents} from "../../../../utilites/helpers.ts";

interface ContinueButtonProps {
    isLoading: boolean;
    buttonContent?: ReactNode;
    order: Order;
    event: Event;
    isOrderComplete?: boolean;
    onClick?: () => void;
}

export const CheckoutFooter = ({
                                   isLoading,
                                   buttonContent,
                                   event,
                                   order,
                                   onClick,
                                   isOrderComplete = false
                               }: ContinueButtonProps) => {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (
        <>
            {isSidebarOpen && <div className={classes.overlay} onClick={() => setIsSidebarOpen(false)}/>}

            <div className={classNames(classes.footer, isOrderComplete ? classes.orderComplete : '')}>
                {isSidebarOpen && <CheckoutSidebar event={event} order={order} className={classes.sidebar}/>}

                <div className={classes.buttons}>
                    {!isOrderComplete && (
                        <>
                            {1 && (
                                <Text size="xs" c="dimmed" className={classes.tosNotice}>
                                    <Trans>
                                        By continuing, you agree to the{' '}
                                        <a
                                            href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service') as string}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {getConfig('VITE_APP_NAME', 'Hi.Events')} Terms of Service
                                        </a>
                                    </Trans>
                                </Text>
                            )}
                            <Button
                                className={classes.continueButton}
                                loading={isLoading}
                                size="md"
                                type="submit"
                                onClick={onClick}
                            >
                                {buttonContent || t`Continue`}
                            </Button>
                        </>
                    )}
                    <ActionIcon onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                                variant={'transparent'}
                                size={'md'}
                                className={classes.orderSummaryToggle}
                    >
                        {isSidebarOpen && <IconShoppingCartDown stroke={2}/>}
                        {!isSidebarOpen && <IconShoppingCartUp stroke={2}/>}
                    </ActionIcon>
                </div>
            </div>
        </>
    );
}
