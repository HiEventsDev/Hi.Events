import {QuestionAnswer} from "../../../types.ts";
import {ActionIcon, Group, Text, Tooltip} from '@mantine/core';
import {t} from "@lingui/macro";
import {NavLink} from "react-router-dom";
import {IconExternalLink, IconMessageCircle2, IconPackage, IconShoppingCart, IconUser} from "@tabler/icons-react";
import classes from './QuestionAndAnswerList.module.scss';

interface QuestionAndAnswerListProps {
    questionAnswers: QuestionAnswer[];
    belongsToFilter?: string[];
}

export const QuestionAndAnswerList = ({questionAnswers, belongsToFilter}: QuestionAndAnswerListProps) => {
    const filteredQuestions = belongsToFilter?.length
        ? questionAnswers.filter(qa => belongsToFilter.includes(qa.belongs_to))
        : questionAnswers;

    const productQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && !qa.attendee_id);
    const attendeeQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && qa.attendee_id);
    const orderQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'ORDER');

    const renderSection = (title: string, questions: QuestionAnswer[]) => {
        const getIcon = () => {
            switch (title) {
                case 'Attendee Answers':
                    return <IconUser size={20} stroke={1.5}/>;
                case 'Order Answers':
                    return <IconShoppingCart size={20} stroke={1.5}/>;
                case 'Product Answers':
                    return <IconPackage size={20} stroke={1.5}/>;
                default:
                    return null;
            }
        };

        return (
            <div className={classes.section}>
                <Group justify="space-between" className={classes.sectionHeader}>
                    <Group gap="xs">
                        {getIcon()}
                        <Text fw={600} size="sm">{title}</Text>
                    </Group>
                    <Text size="sm" c="dimmed">
                        {questions.length} {questions.length === 1 ? 'response' : 'responses'}
                    </Text>
                </Group>

                {questions.length > 0 ? (
                    <div className={classes.questionsList}>
                        {questions.map((qa, index) => (
                            <div key={index} className={classes.questionCard}>
                                {qa.product_title && (
                                    <Text size="sm" className={classes.productTitle}>
                                        {qa.product_title}
                                    </Text>
                                )}

                                <div className={classes.questionTitle}>
                                    <IconMessageCircle2
                                        size={16}
                                        stroke={1.5}
                                        style={{marginTop: 3}}
                                    />
                                    <Text size="sm" fw={500}>
                                        {qa.title}
                                    </Text>
                                </div>

                                <Text size="sm" className={classes.answer}>
                                    {/*{Array.isArray(qa.answer) ? qa.answer.join(", ") : qa.answer}*/}
                                    ddd
                                </Text>

                                {qa.attendee_id && (
                                    <div className={classes.attendeeInfo}>
                                        <IconUser size={14} stroke={1.5}/>
                                        <Text size="sm" span>
                                            {qa.first_name
                                                ? `${qa.first_name} ${qa.last_name}`
                                                : t`N/A`}
                                        </Text>
                                        <Tooltip
                                            label={t`Navigate to Attendee`}
                                            position="bottom"
                                            withArrow
                                        >
                                            <NavLink to={`../attendees?query=${qa.attendee_id}`}>
                                                <ActionIcon
                                                    variant="subtle"
                                                    radius="xl"
                                                    size="xs"
                                                >
                                                    <IconExternalLink size={12}/>
                                                </ActionIcon>
                                            </NavLink>
                                        </Tooltip>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className={classes.emptyState}>
                        <Text size="sm" c="dimmed">
                            {t`No ${title.toLowerCase()} available.`}
                        </Text>
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className={classes.container}>
            {orderQuestions.length > 0 && renderSection('Order Answers', orderQuestions)}
            {attendeeQuestions.length > 0 && renderSection('Attendee Answers', attendeeQuestions)}
            {productQuestions.length > 0 && renderSection('Product Answers', productQuestions)}
        </div>
    );
};
