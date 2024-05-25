import {IdParam, MessageType, Ticket, TicketPrice, TicketType} from "../../../../types.ts";
import {useSortable} from "@dnd-kit/sortable";
import {useDisclosure} from "@mantine/hooks";
import {useState} from "react";
import {useDeleteTicket} from "../../../../mutations/useDeleteTicket.ts";
import {CSS} from "@dnd-kit/utilities";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {relativeDate} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {Card} from "../../Card";
import classes from "../TicketsTable.module.scss";
import classNames from "classnames";
import {IconDotsVertical, IconEyeOff, IconGripVertical, IconPencil, IconSend, IconTrash} from "@tabler/icons-react";
import Truncate from "../../Truncate";
import {Badge, Button, Group, Menu, Popover} from "@mantine/core";
import {EditTicketModal} from "../../../modals/EditTicketModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";
import {UniqueIdentifier} from "@dnd-kit/core";

export const SortableTicket = ({ticket, enableSorting}: {ticket: Ticket, enableSorting: boolean }) => {
    const uniqueId = ticket.id as UniqueIdentifier;
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition
    } = useSortable(
        {
            id: uniqueId,
        }
    );
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [ticketId, setTicketId] = useState<IdParam>();
    const deleteMutation = useDeleteTicket();

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const handleModalClick = (ticketId: IdParam, modal: { open: () => void }) => {
        setTicketId(ticketId);
        modal.open();
    }

    const handleDeleteTicket = (ticketId: IdParam, eventId: IdParam) => {
        deleteMutation.mutate({ticketId, eventId}, {
            onSuccess: () => {
                showSuccess(t`Ticket deleted successfully`)
            },
            onError: (error: any) => {
                if (error.response?.status === 409) {
                    showError(error.response.data.message || t`This ticket cannot be deleted because it is
                     associated with an order. You can hide it instead.`);
                }
            }
        });
    }

    const getTicketStatus = (ticket: Ticket) => {
        if (ticket.is_sold_out) {
            return t`Sold Out`;
        }

        if (ticket.is_before_sale_start_date) {
            return t`On sale` + ' ' + relativeDate(ticket.sale_start_date as string);
        }

        if (ticket.is_after_sale_end_date) {
            return t`Sale ended ` + ' ' + relativeDate(ticket.sale_end_date as string);
        }

        if (ticket.is_hidden) {
            return t`Hidden from public view`;
        }

        return ticket.is_available ? t`On Sale` : t`Not On Sale`;
    }

    const getPriceRange = (ticket: Ticket) => {
        const ticketPrices: TicketPrice[] = ticket.prices as TicketPrice[];

        if (ticket.type !== TicketType.Tiered) {
            if (ticketPrices[0].price <= 0) {
                return t`Free`;
            }
            return formatCurrency(ticketPrices[0].price);
        }

        if (ticketPrices.length === 0) {
            return formatCurrency(ticketPrices[0].price)
        }

        const prices = ticketPrices.map(ticketPrice => ticketPrice.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);

        if (minPrice <= 0 && maxPrice <= 0) {
            return t`Free`;
        }

        return formatCurrency(minPrice) + ' - ' + formatCurrency(maxPrice);
    }

    return (
        <>
            <div ref={setNodeRef} style={style}>
                <Card className={classes.ticketCard}>
                    <div
                        {...attributes}
                        {...listeners}
                        title={enableSorting ? t`Drag to sort` : t`Sorting is disabled while filters and sorting are applied`}
                        className={classNames(['drag-handle', classes.dragHandle, !enableSorting && classes.dragHandleDisabled])}>
                        <IconGripVertical size={'25px'}/>
                    </div>
                    <div className={classes.ticketInfo}>
                        <div className={classes.ticketDetails}>
                            <div className={classes.title}>
                                <div className={classes.heading}>{t`Title`}</div>
                                <Truncate text={ticket.title}
                                          length={60}/> {(ticket.is_hidden_without_promo_code || ticket.is_hidden) && (
                                <Popover>
                                    <Popover.Target>
                                        <IconEyeOff style={{cursor: 'pointer'}} size={14}/>
                                    </Popover.Target>
                                    <Popover.Dropdown>
                                        {ticket.is_hidden
                                            ? t`This ticket is hidden from public view`
                                            : t`This ticket is hidden unless targeted by a Promo Code`}
                                    </Popover.Dropdown>
                                </Popover>
                            )}
                            </div>
                            <div className={classes.description}>
                                <div className={classes.heading}>{t`Status`}</div>
                                <Popover>
                                    <Popover.Target>
                                        <Badge className={classes.status}
                                               color={ticket.is_available ? 'green' : 'orange'} variant={"outline"}>
                                            {ticket.is_available ? t`On Sale` : t`Not On Sale`}
                                        </Badge>
                                    </Popover.Target>
                                    <Popover.Dropdown>
                                        {getTicketStatus(ticket)}
                                    </Popover.Dropdown>
                                </Popover>

                            </div>
                            <div className={classes.price}>
                                <div className={classes.heading}>{t`Price`}</div>
                                <div className={classes.priceAmount}>
                                    {getPriceRange(ticket)}
                                </div>
                            </div>
                            <div className={classes.availability}>
                                <div className={classes.heading}>{t`Attendees`}</div>
                                {Number(ticket.quantity_sold)}
                            </div>
                        </div>
                    </div>
                    <div className={classes.action}>
                        <Group wrap={'nowrap'} gap={0}>
                            <Menu shadow="md" width={200}>
                                <Menu.Target>
                                    <div>
                                        <div className={classes.mobileAction}>
                                            <Button size={"xs"} variant={"light"}>
                                                {t`Manage`}
                                            </Button>
                                        </div>
                                        <div className={classes.desktopAction}>
                                            <Button size={"xs"} variant={"transparent"}>
                                                <IconDotsVertical/>
                                            </Button>
                                        </div>
                                    </div>
                                </Menu.Target>

                                <Menu.Dropdown>
                                    <Menu.Label>{t`Actions`}</Menu.Label>
                                    <Menu.Item
                                        onClick={() => handleModalClick(ticket.id, messageModal)}
                                        leftSection={<IconSend
                                            size={14}/>}>{t`Message Attendees`}</Menu.Item>
                                    <Menu.Item
                                        onClick={() => handleModalClick(ticket.id, editModal)}
                                        leftSection={<IconPencil
                                            size={14}/>}>{t`Edit Ticket`}</Menu.Item>

                                    <Menu.Label>{t`Danger zone`}</Menu.Label>
                                    <Menu.Item
                                        onClick={() => handleDeleteTicket(ticket.id, ticket.event_id)}
                                        color="red"
                                        leftSection={<IconTrash size={14}/>}
                                    >
                                        {t`Delete ticket`}
                                    </Menu.Item>
                                </Menu.Dropdown>
                            </Menu>
                        </Group>
                    </div>
                    <div className={classes.halfCircle}/>
                    <div className={`${classes.halfCircle} ${classes.right}`}/>
                </Card>
            </div>

            {isEditModalOpen && <EditTicketModal ticketId={ticketId}
                                                 onClose={editModal.close}
            />}
            {isMessageModalOpen && <SendMessageModal onClose={messageModal.close}
                                                     ticketId={ticketId}
                                                     messageType={MessageType.Ticket}
            />}
        </>
    );
};
