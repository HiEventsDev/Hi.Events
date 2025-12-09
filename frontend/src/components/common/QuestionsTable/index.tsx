import {
    Badge,
    Button,
    Collapse,
    Group,
    Menu,
    SegmentedControl,
    Text,
    Tooltip,
    UnstyledButton,
    ActionIcon,
} from '@mantine/core';
import {IdParam, Question} from "../../../types.ts";
import {
    IconChevronDown,
    IconChevronUp,
    IconDotsVertical,
    IconEye,
    IconEyeOff,
    IconGripVertical,
    IconPencil,
    IconPlus,
    IconShoppingCart,
    IconTableExport,
    IconTrash,
    IconUser,
    IconUsers,
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
import {useExportAnswers} from "../../../mutations/useExportAnswers.ts";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../mutations/useUpdateEventSettings.ts";
import {CustomSelect, ItemProps} from "../CustomSelect";
import {HeadingWithDescription} from "../Card/CardHeading";

interface QuestionsTableProp {
    questions: Partial<Question>[];
    isSystemDefault?: boolean;
    onEditModalOpen?: (id: IdParam) => void;
}

type QuestionType = 'ORDER' | 'PRODUCT';

const SortableQuestion = ({
    question,
    onEditModalOpen,
    onDelete,
}: {
    question: Partial<Question>;
    onEditModalOpen?: (id: IdParam) => void;
    onDelete: (id: IdParam) => void;
}) => {
    const uniqueId = question.id as UniqueIdentifier;
    const {attributes, listeners, setNodeRef, transform, transition, isDragging} = useSortable({
        id: uniqueId,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const isHidden = question.is_hidden;

    return (
        <div
            style={style}
            ref={setNodeRef}
            className={classNames(classes.questionCard, {
                [classes.hidden]: isHidden,
                [classes.dragging]: isDragging,
            })}
        >
            <div {...attributes} {...listeners} className={classes.dragHandle}>
                <IconGripVertical size={18} stroke={1.5}/>
            </div>

            <div className={classes.questionContent}>
                <div className={classes.questionHeader}>
                    <Text fw={500} className={classes.questionTitle}>
                        <Truncate text={question.title}/>
                    </Text>
                    {isHidden && (
                        <Tooltip label={t`Hidden from attendees - only visible to organizers`}>
                            <IconEyeOff size={14} className={classes.hiddenIcon}/>
                        </Tooltip>
                    )}
                    {question.required && (
                        <Badge size="xs" variant="light" color="red">
                            {t`Required`}
                        </Badge>
                    )}
                </div>
                <Text size="xs" c="dimmed" className={classes.questionMeta}>
                    {question.type?.replace('_', ' ').toLowerCase()}
                    {question.options && question.options.length > 0 && (
                        <> · {question.options.length} {t`options`}</>
                    )}
                </Text>
            </div>

            <Menu shadow="md" width={180} position="bottom-end">
                <Menu.Target>
                    <ActionIcon variant="subtle" color="gray" size="sm">
                        <IconDotsVertical size={16}/>
                    </ActionIcon>
                </Menu.Target>

                <Menu.Dropdown>
                    <Menu.Item
                        leftSection={<IconPencil size={14}/>}
                        onClick={() => onEditModalOpen?.(question.id)}
                    >
                        {t`Edit`}
                    </Menu.Item>
                    <Menu.Divider/>
                    <Menu.Item
                        color="red"
                        leftSection={<IconTrash size={14}/>}
                        onClick={() => onDelete(question.id)}
                    >
                        {t`Delete`}
                    </Menu.Item>
                </Menu.Dropdown>
            </Menu>
        </div>
    );
};

interface QuestionsListProps extends QuestionsTableProp {
    emptyMessage: string;
    emptyHint: string;
    onAddQuestion: () => void;
}

const QuestionsList = ({
    questions,
    onEditModalOpen,
    emptyMessage,
    emptyHint,
    onAddQuestion,
}: QuestionsListProps) => {
    const {eventId} = useParams();
    const deleteQuestionMutation = useDeleteQuestion();
    const sortMutation = useSortQuestions();
    const {items, setItems, handleDragEnd} = useDragItemsHandler({
        initialItemIds: questions.map((question) => Number(question.id)),
        onSortEnd: (newArray) => {
            sortMutation.mutate(
                {
                    sortedQuestionIds: newArray.map((id, index) => ({id, order: index + 1})),
                    eventId: eventId,
                },
                {
                    onSuccess: () => showSuccess(t`Questions reordered`),
                    onError: () => showError(t`Failed to reorder questions`),
                }
            );
        },
    });

    useEffect(() => {
        setItems(questions.map((question) => Number(question.id)));
    }, [questions]);

    const sensors = useSensors(useSensor(PointerSensor), useSensor(TouchSensor));

    const onDelete = (id: IdParam) => {
        confirmationDialog(t`Delete this question? This cannot be undone.`, () => {
            deleteQuestionMutation.mutate(
                {eventId, questionId: id},
                {
                    onSuccess: () => showSuccess(t`Question deleted`),
                    onError: (error: any) =>
                        showError(error?.response?.data?.message || t`Failed to delete question`),
                }
            );
        });
    };

    if (questions.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="sm" fw={500} mb={4}>
                    {emptyMessage}
                </Text>
                <Text size="xs" c="dimmed" mb="sm">
                    {emptyHint}
                </Text>
                <Button
                    variant="light"
                    size="xs"
                    leftSection={<IconPlus size={14}/>}
                    onClick={onAddQuestion}
                >
                    {t`Add Question`}
                </Button>
            </div>
        );
    }

    return (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={items as UniqueIdentifier[]} strategy={verticalListSortingStrategy}>
                <div className={classes.questionsList}>
                    {questions.map((question) => (
                        <SortableQuestion
                            key={question.id}
                            question={question}
                            onEditModalOpen={onEditModalOpen}
                            onDelete={onDelete}
                        />
                    ))}
                </div>
            </SortableContext>
        </DndContext>
    );
};

const DefaultFieldsPreview = () => (
    <div className={classes.defaultFields}>
        <div className={classes.defaultFieldsGrid}>
            <span className={classes.fieldPill}>{t`First Name`}</span>
            <span className={classes.fieldPill}>{t`Last Name`}</span>
            <span className={classes.fieldPill}>{t`Email`}</span>
        </div>
    </div>
);

const LivePreview = ({
    questions,
    isPerOrderCollection,
}: {
    questions: Partial<Question>[];
    isPerOrderCollection: boolean;
}) => {
    const form = useForm();
    const [isOpen, setIsOpen] = useState(false);

    form.getInputProps = (name: string) => ({
        id: name,
        value: form.values[name],
        onChange: () => void 0,
    });

    const orderQuestions = questions.filter((q) => q.belongs_to === 'ORDER' && !q.is_hidden);
    const productQuestions = questions.filter((q) => q.belongs_to === 'PRODUCT' && !q.is_hidden);

    return (
        <Card className={classes.previewCard}>
            <UnstyledButton
                className={classes.previewToggle}
                onClick={() => setIsOpen(!isOpen)}
            >
                <Group gap="xs">
                    <IconEye size={16}/>
                    <Text size="sm" fw={500}>{t`Preview checkout form`}</Text>
                </Group>
                {isOpen ? <IconChevronUp size={16}/> : <IconChevronDown size={16}/>}
            </UnstyledButton>

            <Collapse in={isOpen}>
                <div className={classes.previewContent}>
                    <div className={classes.previewPane}>
                        <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb={6}>
                            {t`Order`}
                        </Text>
                        <DefaultFieldsPreview/>
                        {orderQuestions.length > 0 && (
                            <div className={classes.previewQuestions}>
                                {orderQuestions.map((q) => (
                                    <QuestionInput
                                        key={q.id}
                                        question={q}
                                        name={String(q.id)}
                                        form={form}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    <div className={classes.previewDivider}/>

                    <div className={classes.previewPane}>
                        <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb={6}>
                            {t`Per Attendee`}
                        </Text>
                        {!isPerOrderCollection && <DefaultFieldsPreview/>}
                        {productQuestions.length > 0 ? (
                            <div className={classes.previewQuestions}>
                                {productQuestions.map((q) => (
                                    <QuestionInput
                                        key={q.id}
                                        question={q}
                                        name={String(q.id)}
                                        form={form}
                                    />
                                ))}
                            </div>
                        ) : isPerOrderCollection ? (
                            <Text size="xs" c="dimmed" fs="italic">
                                {t`Attendee details copied from order`}
                            </Text>
                        ) : null}
                    </div>
                </div>
            </Collapse>
        </Card>
    );
};

export const QuestionsTable = ({questions}: QuestionsTableProp) => {
    const {eventId} = useParams();
    const [activeTab, setActiveTab] = useState<QuestionType>('ORDER');
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [questionId, setQuestionId] = useState<IdParam>();

    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateSettingsMutation = useUpdateEventSettings();
    const collectionMethod = eventSettingsQuery.data?.attendee_details_collection_method || 'PER_TICKET';
    const isPerOrderCollection = collectionMethod === 'PER_ORDER';

    const orderQuestions = questions.filter((q) => q.belongs_to === 'ORDER');
    const productQuestions = questions.filter((q) => q.belongs_to === 'PRODUCT');
    const activeQuestions = activeTab === 'ORDER' ? orderQuestions : productQuestions;

    const handleModalOpen = (id: IdParam) => {
        setQuestionId(id);
        openEditModal();
    };

    const onCompleted = () => {
        // Question created successfully
    };

    const handleCollectionMethodChange = (value: string | string[]) => {
        const val = Array.isArray(value) ? value[0] : value;
        if (!val) return;
        updateSettingsMutation.mutate({
            eventSettings: {attendee_details_collection_method: val as 'PER_TICKET' | 'PER_ORDER'},
            eventId: eventId,
        }, {
            onSuccess: () => showSuccess(t`Setting updated`),
            onError: () => showError(t`Failed to update setting`),
        });
    };

    const collectionMethodOptions: ItemProps[] = [
        {
            icon: <IconUsers size={20}/>,
            label: t`Collect details per ticket`,
            value: 'PER_TICKET',
            description: t`Ask for name and email for each ticket purchased`,
        },
        {
            icon: <IconUser size={20}/>,
            label: t`Collect details per order`,
            value: 'PER_ORDER',
            description: t`Use the buyer's details for all attendees`,
        },
    ];

    const ExportButton = () => {
        const {startExport, isExporting} = useExportAnswers(eventId);
        return (
            <Button
                variant="subtle"
                color="gray"
                leftSection={<IconTableExport size={16}/>}
                loading={isExporting}
                onClick={() => startExport()}
            >
                {t`Export Answers`}
            </Button>
        );
    };

    return (
        <div className={classes.container}>
            <PageTitle
                subheading={t`Customize the questions asked during checkout to gather important information from your attendees.`}
            >{t`Registration Questions`}</PageTitle>

            <Card>
                <HeadingWithDescription
                    heading={t`Attendee Information`}
                    description={t`Configure how attendee details are collected during checkout`}
                />

                <CustomSelect
                    optionList={collectionMethodOptions}
                    name="attendee_details_collection_method"
                    label={t`Attendee details collection`}
                    value={collectionMethod}
                    onChange={handleCollectionMethodChange}
                    disabled={updateSettingsMutation.isPending}
                />
            </Card>

            <Card>
                <HeadingWithDescription
                    heading={t`Custom Questions`}
                    description={t`Add custom questions to collect additional information during checkout`}
                />

                <div className={classes.actionsRow}>
                    <SegmentedControl
                        value={activeTab}
                        onChange={(value) => setActiveTab(value as QuestionType)}
                        className={classes.segmentedControl}
                        data={[
                            {
                                value: 'ORDER',
                                label: (
                                    <Group gap={6} justify="center" wrap="nowrap">
                                        <IconShoppingCart size={16}/>
                                        <span className={classes.tabLabel}>{t`Order`}</span>
                                        {orderQuestions.length > 0 && (
                                            <Badge size="xs" variant="light">
                                                {orderQuestions.length}
                                            </Badge>
                                        )}
                                    </Group>
                                ),
                            },
                            {
                                value: 'PRODUCT',
                                label: (
                                    <Group gap={6} justify="center" wrap="nowrap">
                                        <IconUser size={16}/>
                                        <span className={classes.tabLabel}>{t`Attendee`}</span>
                                        {productQuestions.length > 0 && (
                                            <Badge size="xs" variant="light">
                                                {productQuestions.length}
                                            </Badge>
                                        )}
                                    </Group>
                                ),
                            },
                        ]}
                    />
                    <Group gap="xs" wrap="nowrap">
                        <ExportButton/>
                        <Button
                            leftSection={<IconPlus size={16}/>}
                            onClick={openCreateModal}
                        >
                            {t`Add Question`}
                        </Button>
                    </Group>
                </div>

                <QuestionsList
                    questions={activeQuestions}
                    onEditModalOpen={handleModalOpen}
                    onAddQuestion={openCreateModal}
                    emptyMessage={
                        activeTab === 'ORDER'
                            ? t`No order questions yet`
                            : t`No attendee questions yet`
                    }
                    emptyHint={
                        activeTab === 'ORDER'
                            ? t`Examples: "How did you hear about us?", "Company name for invoice"`
                            : t`Examples: "T-shirt size", "Meal preference", "Job title"`
                    }
                />
            </Card>

            <LivePreview
                questions={questions}
                isPerOrderCollection={isPerOrderCollection}
            />

            {createModalOpen && (
                <CreateQuestionModal
                    onCompleted={onCompleted}
                    onClose={closeCreateModal}
                    defaultBelongsTo={activeTab}
                />
            )}
            {editModalOpen && questionId && (
                <EditQuestionModal questionId={questionId} onClose={closeEditModal}/>
            )}
        </div>
    );
};
