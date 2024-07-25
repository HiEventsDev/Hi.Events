import {CapacityAssignment} from "../../../types";
import {Badge, Button, Progress} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconDotsVertical, IconHelp, IconPlus} from "@tabler/icons-react";
import Truncate from "../Truncate";
import {NoResultsSplash} from "../NoResultsSplash";
import classes from './CapacityAssignmentList.module.scss';
import {Card} from "../Card";
import {Popover} from "../Popover";
import {SearchBar} from "../SearchBar";
import {useState} from "react";

interface CapacityAssignmentListProps {
    capacityAssignments: CapacityAssignment[];
    openCreateModal: () => void;
}

export const CapacityAssignmentList = ({capacityAssignments, openCreateModal}: CapacityAssignmentListProps) => {
    const [searchValue, setSearchValue] = useState<string>('');


    if (capacityAssignments.length === 0) {
        return (
            <NoResultsSplash
                heading={t`No Capacity Assignments`}
                imageHref={'/blank-slate/attendees.svg'}
                subHeading={(
                    <>
                        <p>
                            <Trans>
                                <p>
                                    Capacity assignments allow you to manage capacity for specific tickets or the entire
                                    event.
                                </p>

                                Example:
                                <Card>
                                    If you have a <b>Day 1</b> ticket and an <b>All Weekend</b> ticket, you can create a
                                    capacity assignment called <b>Day 1 Capacity</b> which will ensure you don't go over
                                    capacity on
                                    the first day.
                                </Card>
                            </Trans>
                        </p>
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Create Capacity Assignment`}
                        </Button>
                    </>
                )}
            />
        );
    }

    return (
        <>
            <Card>
                <div className={classes.toolbar}>
                    <div className={classes.search}>
                        <SearchBar
                            onClear={() => setSearchValue('')}
                            value={searchValue}
                            onChange={(value) => setSearchValue(value.target.value)}
                        />
                    </div>
                    <div className={classes.button}>
                        <Button color={'green'} rightSection={<IconPlus/>} onClick={openCreateModal}>
                            {t`Add capacity assignment`}
                        </Button>
                    </div>
                </div>
            </Card>

            <div className={classes.capacityAssignmentList}>
                {capacityAssignments.map((assignment) => {
                    const capacityPercentage = assignment.capacity
                        ? (assignment.used_capacity / assignment.capacity) * 100
                        : 0;
                    const capacityColor = capacityPercentage > 80 ? 'red' : capacityPercentage > 50 ? 'yellow' : 'green';

                    if (searchValue && !assignment.name.toLowerCase().includes(searchValue.toLowerCase())) {
                        return null;
                    }

                    return (
                        <Card className={classes.capacityCard} key={assignment.id}>
                            <div className={classes.capacityAssignmentHeader}>
                                <div className={classes.capacityAssignmentAppliesTo}>
                                    {assignment.applies_to === 'EVENT' && (
                                        <div className={classes.appliesToText}>
                                            {t`Applies to entire event`}
                                        </div>
                                    )}

                                    {assignment.applies_to === 'TICKETS' && (
                                        <Popover
                                            title={assignment.tickets.map((ticket) => (
                                                <div key={ticket.id}>
                                                    {ticket.title}
                                                </div>
                                            ))}
                                            position={'bottom'}
                                            withArrow
                                        >
                                            <div className={classes.appliesToText}>
                                                <div>
                                                    {assignment.tickets.length > 1 &&
                                                        <Trans>Applies to {assignment.tickets.length} tickets</Trans>}
                                                    {assignment.tickets.length === 1 && t`Applies to 1 ticket`}
                                                </div>
                                                &nbsp;
                                                <IconHelp size={16}/>
                                            </div>
                                        </Popover>
                                    )}
                                </div>

                                <div className={classes.capacityAssignmentStatus}>
                                    <Badge variant={'light'} color={assignment.status === 'ACTIVE' ? 'green' : 'gray'}>
                                        {assignment.status}
                                    </Badge>
                                </div>
                            </div>
                            <div className={classes.capacityAssignmentName}>
                                <b>
                                    <Truncate text={assignment.name} length={30}/>
                                </b>
                            </div>

                            <div className={classes.capacityAssignmentInfo}>
                                <div className={classes.capacityAssignmentCapacity}>
                                    {assignment.capacity ? (
                                        <div className={classes.capacity}>
                                            <Progress
                                                className={classes.capacityBar}
                                                value={capacityPercentage}
                                                color={capacityColor}
                                                size={'md'}
                                            />
                                            <div className={classes.capacityText}>
                                                {assignment.used_capacity}/{assignment.capacity}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className={classes.capacityText}>
                                            {assignment.used_capacity}/∞
                                        </div>
                                    )}
                                </div>
                                <div className={classes.capacityAssignmentActions}>
                                    <Button variant={'transparent'}>
                                        <IconDotsVertical/>
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    );
                })}
            </div>
        </>
    );
};

// Dummy handlers for menu actions (should be replaced with actual implementations)
const handleModalClick = (assignment: CapacityAssignment, modalOpen: any) => {
    console.log('Opening modal for:', assignment);
};

const handleDelete = (assignment: CapacityAssignment) => {
    return () => {
        console.log('Deleting assignment:', assignment);
    };
};

const viewModalOpen = {};  // Placeholder for actual modal open state
const editModal = {};      // Placeholder for actual modal open state
