import {ActionIcon, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconChevronsDown, IconChevronsUp} from "@tabler/icons-react";
import classes from "./CheckoutFooter.module.scss";
import {Event, Order} from "../../../../types.ts";
import {CheckoutSidebar} from "../CheckoutSidebar";
import {useState} from "react";
import classNames from "classnames";
import {Currency} from "../../../common/Currency";


interface ContinueButtonProps {
    isLoading: boolean;
    buttonText?: string;
    order: Order;
    event: Event;
    isOrderComplete?: boolean;
}

export const CheckoutFooter = ({isLoading, buttonText, event, order, isOrderComplete = false, showFreeWhenZeroTotal = true}: ContinueButtonProps) => {
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (
        <>
            {isSidebarOpen && <div className={classes.overlay} onClick={() => setIsSidebarOpen(false)}/>}

            <div className={classNames(classes.footer, isOrderComplete ? classes.orderComplete : '')}>
                {isSidebarOpen && <CheckoutSidebar event={event} order={order} className={classes.sidebar}/>}

                <div className={classes.buttons}>
                    <div className={classes.orderTotal}>
                        <div className={classes.itemRow}>
                            <div className={classes.itemName}><b className={classes.total}>{t`Total`}</b></div>
                            <div className={classes.itemValue}>
                                <Currency
                                    currency={event.currency}
                                    price={order.total_gross}
                                    freeLabel={showFreeWhenZeroTotal ? t`Free` : null}
                                />
                            </div>
                        </div>
                    </div>
                    {!isOrderComplete && (
                        <Button
                            className={classes.continueButton}
                            loading={isLoading}
                            type="submit"
                            size="md"
                        >
                            {buttonText || t`Continue`}
                        </Button>
                    )}
                    <ActionIcon onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                                variant={'transparent'}
                                size={'md'}
                                className={classes.orderSummaryToggle}
                    >
                        {isSidebarOpen ? <IconChevronsDown stroke={2}/> : <IconChevronsUp stroke={2}/>}
                    </ActionIcon>
                </div>
            </div>
        </>
    );
}