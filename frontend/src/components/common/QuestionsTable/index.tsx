import {Button, Group, Menu, Switch, TextInput, Tooltip} from '@mantine/core';
import {IdParam, Question} from "../../../types.ts";
import {
    IconDotsVertical,
    IconEye,
    IconEyeClosed,
    IconGripVertical,
    IconInfoCircle,
    IconPencil,
    IconPlus,
    IconTableExport,
    IconTrash
} from "@tabler/icons-react";
import Truncate from "../Truncate";
import classes from "./QuestionsTable.module.scss";
import {QuestionInput} from "../CheckoutQuestion";
import {useForm} from "@mantine/form";
import {PageTitle} from "../PageTitle";
import {CreateQuestionModal} from "../../modals/CreateQuestionModal";
import {useDisclosure} from "@mantine/hooks";
import {Card} from "../Card";
import {t} from "@lingui/macro";
import {useEffect, useState} from "react";
import {EditQuestionModal} from "../../modals/EditQuestionModal";
import {useDeleteQuestion} from "../../../mutations/useDeleteQuestion.ts";
import {useParams} from "react-router";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {InputGroup} from "../InputGroup";
import {useDragItemsHandler} from "../../../hooks/useDragItemsHandler.ts";
import {
    closestCenter,
    DndContext,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useSensor,
    useSensors
} from "@dnd-kit/core";
import {SortableContext, useSortable, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {useSortQuestions} from "../../../mutations/useSortQuestions.ts";
import classNames from "classnames";
import {Popover} from "../Popover";
import {useExportAnswers} from "../../../mutations/useExportAnswers.ts";

interface QuestionsTableProp {
    questions: Partial<Question>[];
    isSystemDefault?: boolean;
    onEditModalOpen?: (id: IdParam) => void;
    showHiddenQuestions?: boolean;
}

const SortableQuestion = ({question, onEditModalOpen, onDelete}: {
    question: Partial<Question>,
    onEditModalOpen?: (id: IdParam) => void,
    onDelete: (id: IdParam) => void,
    isSystemDefault?: boolean
}) => {
    const uniqueId = question.id as UniqueIdentifier;
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

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div style={style} ref={setNodeRef}>
            <Card className={`${classes.questionCard} ${question.is_hidden && classes.hidden}`}>
                <div
                    {...attributes}
                    {...listeners}
                    className={classNames([classes.dragHandle, 'drag-handle'])}>
                    <IconGripVertical size="1.05rem" stroke={1.5}/>
                </div>

                <div className={classes.title}>
                    <Truncate text={question.title}/> {question.is_hidden && (
                    <Tooltip label={t`This question is only visible to the event organizer`}>
                        <IconEyeClosed className={classes.hiddenIcon} size={14}/>
                    </Tooltip>
                )}
                </div>
                <div className={classes.options}>
                    <Group wrap={'nowrap'} gap={0}>
                        <Menu shadow="md" width={200}>
                            <Menu.Target>
                                <Button
                                    size={'sm'}
                                    variant={'transparent'}
                                >
                                    <IconDotsVertical size={14}/>
                                </Button>
                            </Menu.Target>

                            <Menu.Dropdown>
                                <Menu.Label>{t`Actions`}</Menu.Label>
                                <Menu.Item
                                    onClick={() => onEditModalOpen ? onEditModalOpen(question.id) : null}
                                    leftSection={<IconPencil
                                        size={14}/>}>{t`Edit question`}</Menu.Item>
                                <Menu.Divider/>

                                <Menu.Label>{t`Danger zone`}</Menu.Label>
                                <Menu.Item color="red"
                                           leftSection={<IconTrash size={14}/>}
                                           onClick={() => onDelete(question.id)}
                                >
                                    {t`Delete question`}
                                </Menu.Item>
                            </Menu.Dropdown>
                        </Menu>
                    </Group>
                </div>
            </Card>
        </div>
    );
}

const QuestionsList = ({questions, onEditModalOpen, showHiddenQuestions}: QuestionsTableProp) => {
    const {eventId} = useParams();
    const deleteQuestionMutation = useDeleteQuestion();
    const sortMutation = useSortQuestions();
    const {items, setItems, handleDragEnd} = useDragItemsHandler({
        initialItemIds: questions.map((question) => Number(question.id)),
        onSortEnd: (newArray) => {
            sortMutation.mutate({
                sortedQuestionIds: newArray.map((id, index) => {
                    return {id, order: index + 1};
                }),
                eventId: eventId
            }, {
                onSuccess: () => {
                    showSuccess(t`Questions sorted successfully`);
                },
                onError: () => {
                    showError(t`An error occurred while sorting the questions. Please try again or refresh the page`);
                }
            });
        },
    });

    useEffect(() => {
        setItems(questions.map((question) => Number(question.id)));
    }, [questions]);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor)
    );

    const onDelete = (id: IdParam) => {
        confirmationDialog(t`Are you sure you want to delete this question?`, () => {
            deleteQuestionMutation.mutate({
                eventId: eventId,
                questionId: id
            }, {
                onSuccess: () => {
                    showSuccess(t`Question deleted`)
                },
                onError: (error) => {
                    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                    // @ts-ignore
                    showError(error?.response?.data?.message || t`Failed to delete message. Please try again.`);
                }
            });
        });
    }

    return (
        <DndContext sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
        >
            <SortableContext items={items as UniqueIdentifier[]} strategy={verticalListSortingStrategy}>
                {questions
                    .filter(question => showHiddenQuestions || !question.is_hidden)
                    .map((question) => {
                        return <SortableQuestion
                            key={question.id}
                            question={question}
                            onEditModalOpen={onEditModalOpen}
                            onDelete={onDelete}
                        />
                    })}
            </SortableContext>
        </DndContext>
    );
};

const DefaultQuestions = () => (
    <>
        <InputGroup>
            <TextInput
                withAsterisk
                label={t`First Name`}
                placeholder={t`First name`}
            />
            <TextInput
                withAsterisk
                label={t`Last Name`}
                placeholder={t`Last Name`}
            />
        </InputGroup>

        <TextInput
            withAsterisk
            type={"email"}
            label={t`Email Address`}
            placeholder={t`Email Address`}
        />
    </>
);

export const QuestionsTable = ({questions}: QuestionsTableProp) => {
    const productQuestions = questions.filter(question => question.belongs_to === "PRODUCT");
    const orderQuestions = questions.filter(question => question.belongs_to === "ORDER");
    const form = useForm();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [questionId, setQuestionId] = useState<IdParam>();
    const [showHiddenQuestions, setShowHiddenQuestions] = useState(false);

    // This disables the input fields in the preview
    form.getInputProps = (name: string) => ({
        id: name,
        value: form.values[name],
        onChange: () => {
            void 0
        },
    });

    const handleModalOpen = (questionId: IdParam) => {
        setQuestionId(questionId);
        openEditModal();
    }

    const onCompleted = (question: Question) => {
        if (question.is_hidden && !showHiddenQuestions) {
            setShowHiddenQuestions(true);
            showSuccess(t`You created a hidden question but disabled the option to show hidden questions. It has been enabled.`);
        }
    }

    const ExportAnswersButton = () => {
        const {eventId} = useParams();
        const {startExport, isExporting} = useExportAnswers(eventId);

        return (
            <Button
                loading={isExporting}
                color="green"
                rightSection={<IconTableExport size={20}/>}
                onClick={() => startExport()}
            >
                {t`Export answers`}
            </Button>
        );
    };

    return (
        <div className={classes.outer}>
            <PageTitle>
                <span>{t`Questions`}</span>
            </PageTitle>
            <Card>
                <div className={classes.actions}>
                    <>
                        <Button color={'green'} rightSection={<IconPlus/>} onClick={openCreateModal}>
                            {t`Add question`}
                        </Button>
                        <ExportAnswersButton/>
                    </>
                    <div className={classes.hiddenToggle}>
                        <Group>
                            <span className={classes.hiddenCount}>
                                    {questions.filter(question => question.is_hidden).length}{' '}
                                {questions.filter(question => question.is_hidden).length === 1
                                    ? t`hidden question`
                                    : t`hidden questions`
                                }
                            </span>
                            <Tooltip
                                label={showHiddenQuestions ? t`Hide hidden questions` : t`Show hidden questions`}
                            >
                                <Switch
                                    offLabel={<IconEyeClosed size={14}/>}
                                    onLabel={<IconEye size={14}/>}
                                    size={'sm'}
                                    checked={showHiddenQuestions}
                                    onChange={() => setShowHiddenQuestions(!showHiddenQuestions)}
                                />
                            </Tooltip>
                        </Group>

                    </div>
                </div>
            </Card>
            <div className={classes.container}>
                <div className={classes.questionsContainer}>
                    <div className={classes.questions}>
                        <h3>{t`Order questions`}</h3>
                        <QuestionsList
                            questions={orderQuestions}
                            onEditModalOpen={handleModalOpen}
                            showHiddenQuestions={showHiddenQuestions}
                        />
                        {orderQuestions
                            .filter(question => showHiddenQuestions || !question.is_hidden)
                            .length === 0 && (
                            <Card className={classes.noQuestionsAlert}>
                                <IconInfoCircle/> {t`You have no order questions.`}
                            </Card>
                        )}
                    </div>
                    <div className={classes.questions}>
                        <h3>{t`Product questions`}</h3>
                        <QuestionsList
                            questions={productQuestions}
                            onEditModalOpen={handleModalOpen}
                            showHiddenQuestions={showHiddenQuestions}
                        />
                        {productQuestions
                            .filter(question => showHiddenQuestions || !question.is_hidden)
                            .length === 0 && (
                            <Card className={classes.noQuestionsAlert}>
                                <IconInfoCircle/> {t`You have no attendee questions.`}
                            </Card>
                        )}
                    </div>
                </div>

                <div className={classes.previewContainer}>
                    <h3>
                        <Group>
                            {t`Preview`}
                            <Popover width={'400px'}
                                     title={t`First Name, Last Name, and Email Address are default questions and are always included in the checkout process.`}>
                                <IconInfoCircle size={18}/>
                            </Popover>
                        </Group>
                    </h3>
                    <Card className={classes.previewCard}>
                        <h3>{t`Order questions`}</h3>
                        <div className={classes.previewForm}>
                            <div className={classes.mask}/>
                            <DefaultQuestions/>
                            {orderQuestions
                                .filter(question => showHiddenQuestions || !question.is_hidden)
                                .map(question => (
                                    <QuestionInput key={question.id}
                                                   question={question}
                                                   name={String(question.id)}
                                                   form={form}
                                    />
                                ))}

                            <h3>{t`Attendee questions`}</h3>
                            <DefaultQuestions/>
                            {productQuestions
                                .filter(question => showHiddenQuestions || !question.is_hidden)
                                .map(question => (
                                    <QuestionInput key={question.id}
                                                   question={question}
                                                   name={String(question.id)}
                                                   form={form}
                                    />
                                ))}
                        </div>
                    </Card>
                </div>
            </div>
            {createModalOpen && <CreateQuestionModal onCompleted={onCompleted} onClose={closeCreateModal}/>}
            {(editModalOpen && questionId) && <EditQuestionModal questionId={questionId} onClose={closeEditModal}/>}
        </div>
    );
}
