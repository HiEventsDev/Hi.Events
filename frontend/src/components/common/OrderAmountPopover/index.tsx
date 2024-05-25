import {Event, Order} from "../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {Badge, Flex, MantineColor, Popover} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";
import {formatCurrency} from "../../../utilites/currency.ts";
import {OrderSummary} from "../OrderSummary";
import classes from "./OrderAmountPopover.module.scss";
import {formatStatus} from "../../../utilites/helpers.ts";

interface OrderAmountPopoverProps {
    event: Event;
    order: Order;
}

export const OrderAmountPopover = ({event, order}: OrderAmountPopoverProps) => {
    const [isPopoverOpen, popover] = useDisclosure(false);

    const badgeColor = (): MantineColor => {
        switch (order.refund_status) {
            case 'REFUND_PENDING':
            case 'REFUND_FAILED':
            case 'PARTIALLY_REFUNDED':
                return 'orange';
            case 'REFUNDED':
                return 'red';
            default:
                return 'green';
        }
    }

    return (
        <Popover width={350} position="bottom" withArrow shadow="md" opened={isPopoverOpen}>
            <Popover.Target>
                <Badge variant={'light'} style={{cursor: 'help', border: '1px solid'}} rightSection={
                    <Flex align={"center"}>
                        <IconInfoCircle size={14}/>
                    </Flex>
                } color={badgeColor()} onMouseEnter={popover.open} onMouseLeave={popover.close}>
                    {formatCurrency(order.total_gross, event.currency)}
                </Badge>
            </Popover.Target>
            <Popover.Dropdown style={{pointerEvents: 'none'}}>
                <div className={classes.paymentStatus}>
                    {formatStatus(String(order.payment_status))}
                </div>
                <OrderSummary event={event} order={order} showFreeWhenZeroTotal={false}/>
            </Popover.Dropdown>
        </Popover>
    )
}