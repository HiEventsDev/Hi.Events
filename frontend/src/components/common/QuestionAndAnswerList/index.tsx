import {IdParam, QuestionAnswer} from "../../../types.ts";
import {ActionIcon, Button, Group, Text, Tooltip} from '@mantine/core';
import {t} from "@lingui/macro";
import {
    IconEdit,
    IconExternalLink,
    IconPackage,
    IconShoppingCart,
    IconUser
} from "@tabler/icons-react";
import {NavLink, useParams} from "react-router";
import classes from './QuestionAndAnswerList.module.scss';
import {useEditQuestionAnswer} from "../../../mutations/useEditQuestionAnswer.ts";
import {QuestionInput} from "../CheckoutQuestion";
import {useForm} from "@mantine/form";
import {useState} from "react";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {formatAnswer} from "../../../utilites/questionHelper.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";

interface QuestionAndAnswerListProps {
    questionAnswers: QuestionAnswer[];
    belongsToFilter?: string[];
    onEditAnswer?: () => void;
}

interface QuestionListProps {
    questions: QuestionAnswer[];
    onEditAnswer?: () => void;
    compact?: boolean;
}

export const QuestionList = ({questions, onEditAnswer, compact = false}: QuestionListProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const updateAnswerMutation = useEditQuestionAnswer();
    const {eventId} = useParams();
    const [editingQuestionIds, setEditingQuestionIds] = useState<IdParam[]>([]);

    const toggleEditMode = (questionId: IdParam) => {
        setEditingQuestionIds(prev =>
            prev.includes(questionId)
                ? prev.filter(id => id !== questionId)
                : [...prev, questionId]
        );
    };

    const isEditing = (questionId: IdParam) => {
        return editingQuestionIds.includes(questionId);
    };

    if (!questions.length) {
        return null;
    }

    return (
        <div className={classes.questionsList}>
            {questions.map((qa, index) => {
                const initialValues = qa.question_type === 'ADDRESS' ? {
                    answer: qa.answer,
                } : {
                    answer: {
                        answer: qa.answer,
                    },
                };

                const questionForm = useForm({
                    initialValues: initialValues,
                    transformValues: (values) => ({
                        answer: qa.question_type === 'ADDRESS' ? values.answer : values.answer.answer,
                    }),
                });

                const handleSubmit = (values: { answer: any }) => {
                    updateAnswerMutation.mutate({
                        questionId: qa.question_id,
                        answer: values.answer,
                        answerId: qa.question_answer_id,
                        eventId: eventId,
                    }, {
                        onSuccess: () => {
                            toggleEditMode(qa.question_id);
                            showSuccess(t`Answer updated successfully.`);
                            onEditAnswer?.();
                        },
                        onError: (error) => {
                            errorHandler(questionForm, error);
                            showError(t`Failed to update answer.`);
                        }
                    });
                };

                return (
                    <div key={index} className={compact ? classes.questionCompact : classes.questionCard}>
                        {qa.product_title && !compact && (
                            <Text size="sm" className={classes.productTitle}>
                                {qa.product_title}
                            </Text>
                        )}

                        <div className={classes.questionTitle}>
                            <Text size="sm" fw={600} className={classes.questionText}>
                                {qa.title}
                            </Text>
                        </div>

                        {isEditing(qa.question_id) ? (
                            <form onSubmit={questionForm.onSubmit(handleSubmit)}>
                                <div className={classes.editContainer}>
                                    <QuestionInput
                                        question={{
                                            id: qa.question_id,
                                            title: qa.title,
                                            type: qa.question_type,
                                            options: qa.question_options,
                                            required: qa.question_required,
                                        }}
                                        name="answer"
                                        form={questionForm}
                                    />
                                    <Group gap="xs" className={classes.editActions}>
                                        <Button
                                            type="submit"
                                            variant="light"
                                            size="xs"
                                            loading={updateAnswerMutation.isPending}
                                        >
                                            {t`Save`}
                                        </Button>
                                        <Button
                                            variant="subtle"
                                            size="xs"
                                            onClick={() => toggleEditMode(qa.question_id)}
                                        >
                                            {t`Cancel`}
                                        </Button>
                                    </Group>
                                </div>
                            </form>
                        ) : (
                            <div className={classes.answerContainer}>
                                <Text size="sm" className={classes.answer} style={{whiteSpace: 'pre-line'}}>
                                    {formatAnswer(qa.answer)}
                                </Text>
                                <Tooltip label={t`Edit Answer`} position="bottom" withArrow>
                                    <ActionIcon
                                        variant="subtle"
                                        radius="xl"
                                        size="sm"
                                        onClick={() => toggleEditMode(qa.question_id)}
                                    >
                                        <IconEdit size={16}/>
                                    </ActionIcon>
                                </Tooltip>
                            </div>
                        )}

                        {qa.attendee_id && !compact && (
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
                                    <NavLink to={`../attendees?query=${qa.attendee_public_id}`}>
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
                );
            })}
        </div>
    );
};

export const QuestionAndAnswerList = ({questionAnswers, belongsToFilter, onEditAnswer}: QuestionAndAnswerListProps) => {
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

        if (questions.length === 0) {
            return null;
        }

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

                <QuestionList
                    questions={questions}
                    onEditAnswer={onEditAnswer}
                />
            </div>
        );
    };

    return (
        <div className={classes.container}>
            {renderSection('Order Answers', orderQuestions)}
            {renderSection('Attendee Answers', attendeeQuestions)}
            {renderSection('Product Answers', productQuestions)}
        </div>
    );
};
