import {useState} from "react";
import {t} from "@lingui/macro";
import {Select, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconEye} from "@tabler/icons-react";
import {Product, ProductType, Question, QuestionBelongsToType} from "../../../../types.ts";
import {EligibilityNotice, LockedFields, QuestionInput} from "../../../common/CheckoutQuestion";
import {
    AnswerMap,
    extractAnswer,
    indexQuestions,
    isDisqualifyingAnswer,
    isQuestionShown,
} from "../../../../utilites/questionLogic.ts";
import classes from "./QuestionsBuilder.module.scss";

interface CheckoutPreviewProps {
    questions: Question[];
    products: Product[];
    isPerOrderCollection: boolean;
}

const appliesToProduct = (question: Question, product?: Product): boolean => !!product && (
    (question.applies_to_all_tickets && product.product_type === ProductType.Ticket)
    || !!question.product_ids?.map(Number).includes(Number(product.id))
);

const previewAnswers = (values: Record<string, unknown> | undefined): AnswerMap => Object.fromEntries(
    Object.entries(values ?? {}).map(([key, value]) => [Number(key.slice(1)), extractAnswer(value)])
);

const ContactFields = () => (
    <div className={classes.previewContactFields}>
        <TextInput label={t`First name`} disabled size="xs"/>
        <TextInput label={t`Last name`} disabled size="xs"/>
        <TextInput label={t`Email`} disabled size="xs" className={classes.previewFullWidth}/>
    </div>
);

const PreviewForm = ({questions, product, isPerOrderCollection}: {
    questions: Question[];
    product?: Product;
    isPerOrderCollection: boolean;
}) => {
    const emptyResponses = (belongsTo: QuestionBelongsToType) => Object.fromEntries(
        questions
            .filter((question) => question.belongs_to === belongsTo)
            .map((question) => [`q${question.id}`, {}])
    );
    const form = useForm<{ order: Record<string, unknown>, attendee: Record<string, unknown> }>({
        initialValues: {
            order: emptyResponses(QuestionBelongsToType.ORDER),
            attendee: emptyResponses(QuestionBelongsToType.PRODUCT),
        },
    });
    const questionsById = indexQuestions(questions);
    const orderAnswers = previewAnswers(form.values.order);
    const attendeeAnswers = previewAnswers(form.values.attendee);
    const visibleQuestions = questions.filter((question) => !question.is_hidden);
    const orderQuestions = visibleQuestions.filter((question) => question.belongs_to === QuestionBelongsToType.ORDER
        && isQuestionShown(question, questionsById, orderAnswers));
    const attendeeQuestions = visibleQuestions.filter((question) => question.belongs_to === QuestionBelongsToType.PRODUCT
        && appliesToProduct(question, product)
        && isQuestionShown(question, questionsById, orderAnswers, attendeeAnswers, Number(product?.id)));
    const showAttendeeFields = product?.product_type === ProductType.Ticket && !isPerOrderCollection;
    let isAfterIneligibleAnswer = false;

    const renderQuestion = (question: Question, scope: 'order' | 'attendee') => {
        const answer = (scope === 'order' ? orderAnswers : attendeeAnswers)[Number(question.id)];
        const locked = isAfterIneligibleAnswer;
        isAfterIneligibleAnswer = isAfterIneligibleAnswer || isDisqualifyingAnswer(question, answer);

        return (
            <LockedFields key={question.id} locked={locked}>
                <QuestionInput question={question} name={`${scope}.q${question.id}`} form={form}/>
                {!locked && <EligibilityNotice question={question} answer={answer}/>}
            </LockedFields>
        );
    };

    const renderedOrderQuestions = orderQuestions.map((question) => renderQuestion(question, 'order'));
    const isAttendeeLocked = isAfterIneligibleAnswer;
    isAfterIneligibleAnswer = false;
    const renderedAttendeeQuestions = attendeeQuestions.map((question) => renderQuestion(question, 'attendee'));

    return (
        <>
            <div className={classes.previewGroup}>
                <Text size="xs" fw={600} c="dimmed" className={classes.previewGroupTitle}>{t`Your details`}</Text>
                <ContactFields/>
                {renderedOrderQuestions}
            </div>

            {product && (
                <LockedFields locked={isAttendeeLocked}>
                    <div className={classes.previewGroup}>
                        <Text size="xs" fw={600} c="dimmed" className={classes.previewGroupTitle}>
                            {t`Attendee`} · {product.title}
                        </Text>
                        {showAttendeeFields && <ContactFields/>}
                        {renderedAttendeeQuestions}
                        {!showAttendeeFields && attendeeQuestions.length === 0 && (
                            <Text size="xs" c="dimmed" mt="xs">{t`Nothing extra is asked for this ticket.`}</Text>
                        )}
                    </div>
                </LockedFields>
            )}
        </>
    );
};

export const CheckoutPreview = ({questions, products, isPerOrderCollection}: CheckoutPreviewProps) => {
    const [selectedProductId, setSelectedProductId] = useState<string | null>(null);
    const defaultProduct = products.find((product) => product.product_type === ProductType.Ticket) ?? products[0];
    const product = products.find((candidate) => String(candidate.id) === selectedProductId) ?? defaultProduct;
    const formKey = questions.map((question) => `${question.id}:${question.type}`).join(',');

    return (
        <aside className={classes.preview} aria-label={t`Checkout preview`}>
            <div className={classes.previewHeader}>
                <Text size="sm" fw={600} className={classes.previewTitle}>
                    <IconEye size={16}/>
                    {t`Checkout preview`}
                </Text>
                <Text size="xs" c="dimmed">
                    {t`Try some answers to see follow-up questions. Nothing is saved.`}
                </Text>
            </div>
            {products.length > 1 && (
                <div className={classes.previewTicketBar}>
                    <Select
                        size="xs"
                        label={t`Preview a checkout for`}
                        description={t`Attendee questions can differ by ticket.`}
                        value={product ? String(product.id) : null}
                        onChange={setSelectedProductId}
                        allowDeselect={false}
                        data={products.map((candidate) => ({value: String(candidate.id), label: candidate.title}))}
                    />
                </div>
            )}
            <div className={classes.previewBody}>
                <PreviewForm
                    key={`${formKey}-${product?.id}`}
                    questions={questions}
                    product={product}
                    isPerOrderCollection={isPerOrderCollection}
                />
            </div>
        </aside>
    );
};
