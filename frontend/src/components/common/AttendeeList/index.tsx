import {ActionIcon, Avatar, Collapse, Group, Text, Tooltip} from "@mantine/core";
import {getInitials} from "../../../utilites/helpers.ts";
import {NavLink} from "react-router";
import {IconChevronUp, IconExternalLink, IconMessageCircle2} from "@tabler/icons-react";
import classes from './AttendeeList.module.scss';
import {IdParam, Order, Product, QuestionAnswer} from "../../../types.ts";
import {t} from "@lingui/macro";
import {useState} from "react";
import {QuestionList} from "../QuestionAndAnswerList";

interface AttendeeListProps {
    order: Order;
    products: Product[];
    questionAnswers?: QuestionAnswer[];
    refetchOrder?: () => void;
}

export const AttendeeList = ({order, products, refetchOrder, questionAnswers = []}: AttendeeListProps) => {
    if (!order.attendees?.length) {
        return (
            <div className={classes.container}>
                <Text size="sm" c="dimmed" ta="center" py="xl">
                    {t`No attendees found for this order.`}
                </Text>
            </div>
        );
    }

    const [expandedAttendees, setExpandedAttendees] = useState<IdParam[]>([]);

    const hasQuestions = (attendeeId: IdParam) => {
        return questionAnswers.some(qa => qa.attendee_id === attendeeId);
    };

    const getAttendeeQuestions = (attendeeId: IdParam) => {
        return questionAnswers.filter(qa => qa.attendee_id === attendeeId);
    };

    const toggleExpanded = (attendeeId: IdParam) => {
        setExpandedAttendees(prev =>
            prev.includes(attendeeId)
                ? prev.filter(id => id !== attendeeId)
                : [...prev, attendeeId]
        );
    };

    const isExpanded = (attendeeId: IdParam) => {
        return expandedAttendees.includes(attendeeId);
    };

    return (
        <div className={classes.container}>
            <div className={classes.attendeeList}>
                {order.attendees.map(attendee => {
                    const product = products?.find(p => p.id === attendee.product_id);
                    const fullName = `${attendee.first_name} ${attendee.last_name}`;
                    const attendeeHasQuestions = hasQuestions(attendee.id);

                    return (
                        <div key={attendee.id} className={classes.attendee}>
                            <div className={classes.attendeeInfo}>
                                <Avatar
                                    size="md"
                                    radius="xl"
                                    className={classes.avatar}
                                >
                                    {getInitials(fullName)}
                                </Avatar>

                                <div className={classes.details}>
                                    <Text size="sm" fw={500} className={classes.name}>
                                        {fullName}
                                    </Text>
                                    {product?.title && (
                                        <Text size="xs" className={classes.product} lineClamp={1}>
                                            {product.title}
                                        </Text>
                                    )}
                                </div>

                                <Group gap="xs">
                                    {attendeeHasQuestions && (
                                        <Tooltip
                                            label={isExpanded(attendee.id) ? t`Hide Answers` : t`View Answers`}
                                            position="bottom"
                                            withArrow
                                        >
                                            <ActionIcon
                                                variant="subtle"
                                                radius="xl"
                                                size="sm"
                                                className={classes.actionButton}
                                                onClick={() => toggleExpanded(attendee.id)}
                                            >
                                                {isExpanded(attendee.id)
                                                    ? <IconChevronUp size={16} stroke={1.5}/>
                                                    : <IconMessageCircle2 size={16} stroke={1.5}/>}
                                            </ActionIcon>
                                        </Tooltip>
                                    )}

                                    <Tooltip
                                        label={t`View Attendee Details`}
                                        position="bottom"
                                        withArrow
                                    >
                                        <NavLink to={`../attendees?query=${attendee.public_id}`}>
                                            <ActionIcon
                                                variant="subtle"
                                                radius="xl"
                                                size="sm"
                                                className={classes.actionButton}
                                            >
                                                <IconExternalLink size={16} stroke={1.5}/>
                                            </ActionIcon>
                                        </NavLink>
                                    </Tooltip>
                                </Group>
                            </div>

                            {/* Collapsible answers section */}
                            <Collapse in={isExpanded(attendee.id)}>
                                <div className={classes.answersContainer}>
                                    <QuestionList
                                        compact
                                        questions={getAttendeeQuestions(attendee.id)}
                                        onEditAnswer={refetchOrder}
                                    />
                                </div>
                            </Collapse>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
