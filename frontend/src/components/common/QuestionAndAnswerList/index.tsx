import {IdParam, QuestionAnswer} from "../../../types.ts";
import {ActionIcon, Button, Group, Text, Tooltip} from '@mantine/core';
import {t} from "@lingui/macro";
import {IconEdit, IconExternalLink, IconUser} from "@tabler/icons-react";
import {NavLink, useParams} from "react-router";
import classes from './QuestionAndAnswerList.module.scss';
import {useEditQuestionAnswer} from "../../../mutations/useEditQuestionAnswer.ts";
import {QuestionInput} from "../CheckoutQuestion";
import {useForm} from "@mantine/form";
import {ReactNode, useState} from "react";
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
    hideProductTitle?: boolean;
}

interface QuestionItemProps {
    qa: QuestionAnswer;
    isEditing: boolean;
    toggleEditMode: (id: IdParam) => void;
    onEditAnswer?: () => void;
    eventId?: string;
    hideProductTitle: boolean;
}

const useEditingQuestions = () => {
    const [editingQuestionIds, setEditingQuestionIds] = useState<IdParam[]>([]);

    const toggleEditMode = (questionId: IdParam) => {
        setEditingQuestionIds(prev =>
            prev.includes(questionId)
                ? prev.filter(id => id !== questionId)
                : [...prev, questionId]
        );
    };

    const isEditing = (questionId: IdParam) => editingQuestionIds.includes(questionId);

    return {toggleEditMode, isEditing};
};

const QuestionItem = ({qa, isEditing, toggleEditMode, onEditAnswer, eventId, hideProductTitle}: QuestionItemProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const updateAnswerMutation = useEditQuestionAnswer();

    const questionForm = useForm({
        initialValues: qa.question_type === 'ADDRESS'
            ? {answer: qa.answer}
            : {answer: {answer: qa.answer}},
        transformValues: (values) => ({
            answer: qa.question_type !== 'ADDRESS' && values.answer && typeof values.answer === 'object' && 'answer' in values.answer
                ? values.answer.answer
                : values.answer,
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

    const answer = formatAnswer(qa.answer);

    return (
        <div className={classes.question}>
            <div className={classes.questionHeader}>
                <Text size="xs" className={classes.questionText}>{qa.title}</Text>
                {qa.product_title && !hideProductTitle && (
                    <Text size="xs" className={classes.productTitle}>{qa.product_title}</Text>
                )}
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
                    {answer
                        ? <Text size="sm" className={classes.answer}>{answer}</Text>
                        : <Text size="sm" className={classes.emptyAnswer}>—</Text>}
                    <Tooltip label={t`Edit Answer`} position="bottom" withArrow>
                        <ActionIcon
                            variant="subtle"
                            radius="xl"
                            size="sm"
                            className={classes.editButton}
                            onClick={() => toggleEditMode(qa.question_id)}
                        >
                            <IconEdit size={16}/>
                        </ActionIcon>
                    </Tooltip>
                </div>
            )}
        </div>
    );
};

export const QuestionList = ({questions, onEditAnswer, hideProductTitle = false}: QuestionListProps) => {
    const {eventId} = useParams();
    const {toggleEditMode, isEditing} = useEditingQuestions();

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
                    eventId={eventId}
                    hideProductTitle={hideProductTitle}
                />
            ))}
        </div>
    );
};

const AttendeeQuestionsList = ({questions, onEditAnswer}: { questions: QuestionAnswer[]; onEditAnswer?: () => void }) => {
    const {eventId} = useParams();
    const {toggleEditMode, isEditing} = useEditingQuestions();

    const groupedByAttendee = questions.reduce<Record<string, QuestionAnswer[]>>((groups, qa) => {
        const key = String(qa.attendee_id ?? 'unknown');
        (groups[key] ??= []).push(qa);
        return groups;
    }, {});

    return (
        <div className={classes.attendeeGroups}>
            {Object.entries(groupedByAttendee).map(([attendeeId, attendeeQuestions]) => {
                const attendee = attendeeQuestions[0];
                const name = attendee.first_name ? `${attendee.first_name} ${attendee.last_name}` : t`Unknown Attendee`;

                return (
                    <div key={attendeeId} className={classes.attendeeGroup}>
                        <div className={classes.attendeeHeader}>
                            <IconUser size={14} stroke={1.5} className={classes.attendeeIcon}/>
                            <Text size="sm" fw={600} className={classes.attendeeName}>{name}</Text>
                            {attendee.product_title && (
                                <Text size="xs" className={classes.productTitle}>{attendee.product_title}</Text>
                            )}
                            {attendee.attendee_public_id && (
                                <Tooltip label={t`Navigate to Attendee`} position="bottom" withArrow>
                                    <NavLink to={`../attendees?query=${attendee.attendee_public_id}`}>
                                        <ActionIcon variant="subtle" radius="xl" size="xs">
                                            <IconExternalLink size={14}/>
                                        </ActionIcon>
                                    </NavLink>
                                </Tooltip>
                            )}
                        </div>
                        <div className={classes.questionsList}>
                            {attendeeQuestions.map((qa, index) => (
                                <QuestionItem
                                    key={`${qa.question_id}-${index}`}
                                    qa={qa}
                                    isEditing={isEditing(qa.question_id)}
                                    toggleEditMode={toggleEditMode}
                                    onEditAnswer={onEditAnswer}
                                    eventId={eventId}
                                    hideProductTitle
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

    const orderQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'ORDER');
    const attendeeQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && qa.attendee_id);
    const productQuestions = filteredQuestions.filter(qa => qa.belongs_to === 'PRODUCT' && !qa.attendee_id);

    const renderGroup = (title: string, questions: QuestionAnswer[], content: ReactNode) => {
        if (questions.length === 0) {
            return null;
        }

        return (
            <div className={classes.group}>
                <div className={classes.groupHeader}>
                    <Text size="xs" fw={600} className={classes.groupTitle}>{title}</Text>
                    <span className={classes.groupCount}>{questions.length}</span>
                </div>
                {content}
            </div>
        );
    };

    return (
        <div className={classes.container}>
            {renderGroup(t`Order answers`, orderQuestions, (
                <QuestionList questions={orderQuestions} onEditAnswer={onEditAnswer}/>
            ))}
            {renderGroup(t`Attendee answers`, attendeeQuestions, (
                <AttendeeQuestionsList questions={attendeeQuestions} onEditAnswer={onEditAnswer}/>
            ))}
            {renderGroup(t`Product answers`, productQuestions, (
                <QuestionList questions={productQuestions} onEditAnswer={onEditAnswer}/>
            ))}
        </div>
    );
};
