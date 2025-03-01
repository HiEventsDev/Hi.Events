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

interface AttendeeQuestionsListProps {
    attendeeQuestions: QuestionAnswer[];
    onEditAnswer?: () => void;
    compact?: boolean;
}

interface QuestionItemProps {
    qa: QuestionAnswer;
    isEditing: boolean;
    toggleEditMode: (id: IdParam) => void;
    onEditAnswer?: () => void;
    compact?: boolean;
    eventId?: string;
    hideAttendeeInfo?: boolean;
}

// Separated QuestionItem component to isolate form initialization
const QuestionItem = ({ qa, isEditing, toggleEditMode, onEditAnswer, compact = false, eventId, hideAttendeeInfo = false }: QuestionItemProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const updateAnswerMutation = useEditQuestionAnswer();

    // Form initialization is now isolated in this component
    const initialValues = qa.question_type === 'ADDRESS' ? {
        answer: qa.answer,
    } : {
        answer: {
            answer: qa.answer,
        },
    };

    const questionForm = useForm({
        initialValues: initialValues,
        transformValues: (values) => {
            // Make sure we're handling the transformation consistently
            // For ADDRESS type, just pass the answer directly
            // For other types, extract the nested answer value
            // Also add null/undefined checks
            let transformedAnswer;
            if (qa.question_type === 'ADDRESS') {
                transformedAnswer = values.answer;
            } else {
                // Handle both possible structures to be safe
                transformedAnswer = values.answer && typeof values.answer === 'object' && 'answer' in values.answer
                    ? values.answer.answer
                    : values.answer;
            }

            return {
                answer: transformedAnswer
            };
        },
    });

    const handleSubmit = (values: { answer: any }) => {
        // Don't transform the answer - the form's transformValues has already done this
        // The values parameter here is already the result of the transformValues function
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
        <div className={compact ? classes.questionCompact : classes.questionCard}>
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

            {isEditing ? (
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

            {qa.attendee_id && !compact && !hideAttendeeInfo && (
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
};

export const QuestionList = ({questions, onEditAnswer, compact = false}: QuestionListProps) => {
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
            {questions.map((qa, index) => (
                <QuestionItem
                    key={`${qa.question_id}-${index}`}
                    qa={qa}
                    isEditing={isEditing(qa.question_id)}
                    toggleEditMode={toggleEditMode}
                    onEditAnswer={onEditAnswer}
                    compact={compact}
                    eventId={eventId}
                />
            ))}
        </div>
    );
};

// New component to group questions by attendee
export const AttendeeQuestionsList = ({attendeeQuestions, onEditAnswer, compact = false}: AttendeeQuestionsListProps) => {
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

    if (!attendeeQuestions.length) {
        return null;
    }

    // Group questions by attendee
    const groupedByAttendee: Record<string, QuestionAnswer[]> = {};

    attendeeQuestions.forEach(qa => {
        const attendeeKey = qa.attendee_id || 'unknown';
        if (!groupedByAttendee[attendeeKey]) {
            groupedByAttendee[attendeeKey] = [];
        }
        groupedByAttendee[attendeeKey].push(qa);
    });

    return (
        <div className={classes.attendeeQuestionsList}>
            {Object.entries(groupedByAttendee).map(([attendeeId, questions]) => {
                const attendeeInfo = questions[0]; // Take first question to get attendee info

                return (
                    <div key={attendeeId} className={classes.attendeeSection}>
                        {/* Attendee header with name and link */}
                        <div className={classes.attendeeHeader}>
                            <Group gap="xs">
                                <IconUser size={16} stroke={1.5}/>
                                <Text size="sm" fw={600}>
                                    {attendeeInfo.first_name
                                        ? `${attendeeInfo.first_name} ${attendeeInfo.last_name}`
                                        : t`Unknown Attendee`}
                                </Text>
                            </Group>
                            {attendeeInfo.attendee_public_id && (
                                <Tooltip
                                    label={t`Navigate to Attendee`}
                                    position="bottom"
                                    withArrow
                                >
                                    <NavLink to={`../attendees?query=${attendeeInfo.attendee_public_id}`}>
                                        <ActionIcon
                                            variant="subtle"
                                            radius="xl"
                                            size="xs"
                                        >
                                            <IconExternalLink size={14}/>
                                        </ActionIcon>
                                    </NavLink>
                                </Tooltip>
                            )}
                        </div>

                        {/* Questions for this attendee */}
                        <div className={classes.attendeeQuestions}>
                            {questions.map((qa, index) => (
                                <QuestionItem
                                    key={`${qa.question_id}-${index}`}
                                    qa={qa}
                                    isEditing={isEditing(qa.question_id)}
                                    toggleEditMode={toggleEditMode}
                                    onEditAnswer={onEditAnswer}
                                    compact={compact}
                                    eventId={eventId}
                                    hideAttendeeInfo={true} // Hide attendee info since we're showing it in the header
                                />
                            ))}
                        </div>
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

    const renderSection = (title: string, questions: QuestionAnswer[], isAttendeeSection = false) => {
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

                {isAttendeeSection ? (
                    <AttendeeQuestionsList
                        attendeeQuestions={questions}
                        onEditAnswer={onEditAnswer}
                    />
                ) : (
                    <QuestionList
                        questions={questions}
                        onEditAnswer={onEditAnswer}
                    />
                )}
            </div>
        );
    };

    return (
        <div className={classes.container}>
            {renderSection('Order Answers', orderQuestions)}
            {renderSection('Attendee Answers', attendeeQuestions, true)}
            {renderSection('Product Answers', productQuestions)}
        </div>
    );
};
