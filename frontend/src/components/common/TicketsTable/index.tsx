import {useEffect} from 'react';
import classes from './TicketsTable.module.scss';
import {NoResultsSplash} from "../NoResultsSplash";
import {t} from "@lingui/macro";
import {
    closestCenter,
    DndContext,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {SortableContext, verticalListSortingStrategy,} from '@dnd-kit/sortable';
import {Ticket} from "../../../types";
import {useSortTickets} from "../../../mutations/useSortTickets.ts";
import {useParams} from "react-router-dom";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {SortableTicket} from "./SortableTicket";
import {useDragItemsHandler} from "../../../hooks/useDragItemsHandler.ts";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";

interface TicketCardProps {
    tickets: Ticket[];
    enableSorting: boolean;
    openCreateModal: () => void;
}

export const TicketsTable = ({tickets, openCreateModal, enableSorting = false}: TicketCardProps) => {
    const {eventId} = useParams();
    const sortTicketsMutation = useSortTickets();
    const {items, setItems, handleDragEnd} = useDragItemsHandler({
        initialItemIds: tickets.map((ticket) => Number(ticket.id)),
        onSortEnd: (newArray) => {
            sortTicketsMutation.mutate({
                sortedTickets: newArray.map((id, index) => {
                    return {id, order: index + 1};
                }),
                eventId: eventId,
            }, {
                onSuccess: () => {
                    showSuccess(t`Tickets sorted successfully`);
                },
                onError: () => {
                    showError(t`An error occurred while sorting the tickets. Please try again or refresh the page`);
                }
            })
        },
    });

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor)
    );

    useEffect(() => {
        setItems(tickets.map((ticket) => Number(ticket.id)));
    }, [tickets]);

    if (tickets.length === 0) {
        return <NoResultsSplash
            imageHref={'/blank-slate/tickets.svg'}
            heading={t`No tickets to show`}
            subHeading={(
                <>
                    <p>
                        {t`You'll need at least one ticket to get started. Free, paid or let the user decide what to pay.`}
                    </p>
                    <Button
                        size={'xs'}
                        leftSection={<IconPlus/>}
                        color={'green'}
                        onClick={() => openCreateModal()}>{t`Create a Ticket`}
                    </Button>
                </>
            )}
        />;
    }

    const handleDragStart = (event: any) => {
        if (!enableSorting) {
            showError(t`Please remove filters and set sorting to "Homepage order" to enable sorting`);
            event.cancel();
        }
    }

    return (
        <DndContext onDragStart={handleDragStart}
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
        >
            <SortableContext items={items as UniqueIdentifier[]} strategy={verticalListSortingStrategy}>
                <div className={classes.cards}>
                    {items.map((ticketId) => {
                        const ticket = tickets.find((t) => t.id === ticketId);

                        if (!ticket) {
                            return null;
                        }

                        return (
                            <SortableTicket
                                key={ticketId}
                                ticket={ticket}
                                enableSorting={enableSorting}
                            />
                        );
                    })}
                </div>
            </SortableContext>
        </DndContext>
    );
};
