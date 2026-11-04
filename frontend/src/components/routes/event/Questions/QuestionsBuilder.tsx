import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Text} from "@mantine/core";
import {IconLock, IconTableExport} from "@tabler/icons-react";
import {useParams} from "react-router";
import {
    AttendeeDetailsCollectionMethod,
    IdParam,
    Product,
    Question,
    QuestionBelongsToType,
    QuestionRequestData,
} from "../../../../types.ts";
import {PageTitle} from "../../../common/PageTitle";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useSortQuestions} from "../../../../mutations/useSortQuestions.ts";
import {useDeleteQuestion} from "../../../../mutations/useDeleteQuestion.ts";
import {useEditQuestion} from "../../../../mutations/useEditQuestion.ts";
import {useExportAnswers} from "../../../../mutations/useExportAnswers.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {CreateQuestionModal} from "../../../modals/CreateQuestionModal";
import {EditQuestionModal} from "../../../modals/EditQuestionModal";
import {
    formValuesToRequest,
    getDefaultQuestionValues,
    QuestionFormValues,
    questionToFormValues,
} from "../../../forms/QuestionForm/questionFormValues.ts";
import {indexQuestions} from "../../../../utilites/questionLogic.ts";
import {AddQuestionMenu} from "./AddQuestionMenu.tsx";
import {AttendeeDetailsSetting} from "./AttendeeDetailsSetting.tsx";
import {QuestionSection} from "./QuestionSection.tsx";
import {CheckoutPreview} from "./CheckoutPreview.tsx";
import classes from "./QuestionsBuilder.module.scss";

interface QuestionsBuilderProps {
    questions: Question[];
}

interface CreateDrawerState {
    initialValues: QuestionFormValues;
    heading?: string;
}

const ExportAnswersButton = () => {
    const {eventId} = useParams();
    const {startExport, isExporting} = useExportAnswers(eventId);

    return (
        <Button
            variant="default"
            size="xs"
            leftSection={<IconTableExport size={14}/>}
            loading={isExporting}
            onClick={() => startExport()}
        >
            {t`Export answers`}
        </Button>
    );
};

const FixedFieldsNote = ({children}: { children: string }) => (
    <Text size="xs" c="dimmed" className={classes.fixedFieldsText}>
        <IconLock size={12}/>
        {children}
    </Text>
);

export const QuestionsBuilder = ({questions}: QuestionsBuilderProps) => {
    const {eventId} = useParams();
    const {isReadOnly} = useAccess();
    const [createDrawer, setCreateDrawer] = useState<CreateDrawerState | null>(null);
    const [editingQuestionId, setEditingQuestionId] = useState<IdParam | null>(null);

    const eventQuery = useGetEvent(eventId);
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateSettingsMutation = useUpdateEventSettings();
    const sortMutation = useSortQuestions();
    const deleteMutation = useDeleteQuestion();
    const editMutation = useEditQuestion();

    const products: Product[] = (eventQuery.data?.product_categories ?? [])
        .flatMap((category) => category.products ?? []);
    const productTitles = new Map(products.map((product) => [Number(product.id), product.title]));
    const isPerOrderCollection = eventSettingsQuery.data?.attendee_details_collection_method === 'PER_ORDER';
    const requiresBillingAddress = !!eventSettingsQuery.data?.require_billing_address;

    const orderQuestions = questions.filter((question) => question.belongs_to === QuestionBelongsToType.ORDER);
    const attendeeQuestions = questions.filter((question) => question.belongs_to === QuestionBelongsToType.PRODUCT);

    const openCreateDrawer = (belongsTo: QuestionBelongsToType) => (values: Partial<QuestionRequestData>) => {
        setCreateDrawer({initialValues: getDefaultQuestionValues(belongsTo, values)});
    };

    const saveSortOrder = (sortedIds: IdParam[]) => {
        sortMutation.mutate({
            eventId,
            sortedQuestionIds: sortedIds.map((id, index) => ({id, order: index + 1})),
        }, {
            onSuccess: () => showSuccess(t`Questions reordered`),
            onError: () => showError(t`Failed to reorder questions`),
        });
    };

    const handleDuplicate = (question: Question) => {
        setCreateDrawer({
            initialValues: {...questionToFormValues(question), title: t`${question.title} (copy)`},
            heading: t`Duplicate Question`,
        });
    };

    const handleAddFollowUp = (parent: Question) => {
        const isAttendeeQuestion = parent.belongs_to === QuestionBelongsToType.PRODUCT;

        setCreateDrawer({
            initialValues: getDefaultQuestionValues(parent.belongs_to as QuestionBelongsToType, {
                condition_question_id: String(parent.id),
                applies_to_all_tickets: isAttendeeQuestion && !!parent.applies_to_all_tickets,
                product_ids: isAttendeeQuestion ? (parent.product_ids ?? []).map(String) : [],
            }),
            heading: t`Add follow-up question`,
        });
    };

    const handleToggleHidden = (question: Question) => {
        editMutation.mutate({
            eventId,
            questionId: question.id as IdParam,
            questionData: formValuesToRequest({...questionToFormValues(question), is_hidden: !question.is_hidden}),
        }, {
            onSuccess: () => showSuccess(question.is_hidden ? t`Question shown at checkout` : t`Question hidden at checkout`),
            onError: () => showError(t`Failed to update question`),
        });
    };

    const handleDelete = (question: Question) => {
        const hasFollowUps = questions.some((candidate) => Number(candidate.condition_question_id) === Number(question.id));
        const message = hasFollowUps
            ? t`Delete this question and its follow-up questions? This cannot be undone.`
            : t`Delete this question? This cannot be undone.`;

        confirmationDialog(message, () => {
            deleteMutation.mutate({eventId, questionId: question.id as IdParam}, {
                onSuccess: () => showSuccess(t`Question deleted`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete question`),
            });
        }, {destructive: true});
    };

    const handleCollectionMethodChange = (value: AttendeeDetailsCollectionMethod) => {
        updateSettingsMutation.mutate({
            eventSettings: {attendee_details_collection_method: value},
            eventId,
        }, {
            onSuccess: () => showSuccess(t`Setting updated`),
            onError: () => showError(t`Failed to update setting`),
        });
    };

    const rowHandlers = {
        productTitles,
        questionsById: indexQuestions(questions),
        onEdit: (question: Question) => setEditingQuestionId(question.id as IdParam),
        onDuplicate: handleDuplicate,
        onAddFollowUp: handleAddFollowUp,
        onToggleHidden: handleToggleHidden,
        onDelete: handleDelete,
    };

    return (
        <div className={classes.container}>
            <div className={classes.pageHeader}>
                <PageTitle
                    subheading={t`Choose what buyers and attendees are asked at checkout.`}
                >
                    {t`Registration Questions`}
                </PageTitle>
                <ExportAnswersButton/>
            </div>

            <div className={classes.layout}>
                <div className={classes.builder}>
                    <QuestionSection
                        title={t`Asked once per order`}
                        description={t`Answered by the person placing the order.`}
                        headerActions={(
                            <AddQuestionMenu
                                onSelect={openCreateDrawer(QuestionBelongsToType.ORDER)}
                                dataTestId="question-add-order-button"
                            />
                        )}
                        intro={(
                            <FixedFieldsNote>
                                {requiresBillingAddress
                                    ? t`Name, email and billing address are always collected.`
                                    : t`Name and email are always collected.`}
                            </FixedFieldsNote>
                        )}
                        questions={orderQuestions}
                        emptyMessage={t`No order questions yet`}
                        emptyHint={t`Examples: "How did you hear about us?", "Company name for invoice"`}
                        dataTestId="question-section-order"
                        onSortEnd={(sortedIds) => saveSortOrder([
                            ...sortedIds,
                            ...attendeeQuestions.map((question) => question.id as IdParam),
                        ])}
                        {...rowHandlers}
                    />

                    <QuestionSection
                        title={t`Asked for each attendee`}
                        description={t`Answered once for every ticket in the order.`}
                        headerActions={(
                            <AddQuestionMenu
                                onSelect={openCreateDrawer(QuestionBelongsToType.PRODUCT)}
                                dataTestId="question-add-attendee-button"
                            />
                        )}
                        intro={(
                            <AttendeeDetailsSetting
                                value={isPerOrderCollection ? 'PER_ORDER' : 'PER_TICKET'}
                                disabled={isReadOnly || updateSettingsMutation.isPending}
                                hasAttendeeQuestions={attendeeQuestions.length > 0}
                                onChange={handleCollectionMethodChange}
                            />
                        )}
                        questions={attendeeQuestions}
                        emptyMessage={t`No attendee questions yet`}
                        emptyHint={t`Examples: "T-shirt size", "Meal preference", "Job title"`}
                        dataTestId="question-section-attendee"
                        onSortEnd={(sortedIds) => saveSortOrder([
                            ...orderQuestions.map((question) => question.id as IdParam),
                            ...sortedIds,
                        ])}
                        {...rowHandlers}
                    />
                </div>

                <CheckoutPreview
                    questions={questions}
                    products={products}
                    isPerOrderCollection={isPerOrderCollection}
                />
            </div>

            {createDrawer && (
                <CreateQuestionModal
                    initialValues={createDrawer.initialValues}
                    heading={createDrawer.heading}
                    onClose={() => setCreateDrawer(null)}
                />
            )}
            {editingQuestionId && (
                <EditQuestionModal
                    questionId={editingQuestionId}
                    onClose={() => setEditingQuestionId(null)}
                />
            )}
        </div>
    );
};
