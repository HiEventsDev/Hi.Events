import {useEffect, useState} from "react";
import {Button} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {IconArrowLeft} from "@tabler/icons-react";
import {CheckoutProductQuestions} from "../../../../../components/common/CheckoutQuestion";
import {AttendeeSlot, isQuestionAnswered, QuestionAnswer} from "../../../utilites/boxOfficeQuestions.ts";
import {
    answersByQuestionId,
    extractAnswer,
    indexQuestions,
    isDisqualifyingAnswer,
    isQuestionShown,
} from "../../../../../utilites/questionLogic.ts";
import {Question} from "../../../../../types.ts";
import classes from "./tabs/SellTab.module.scss";

interface AttendeeQuestionsStageProps {
    slots: AttendeeSlot[];
    allQuestions: Question[];
    orderAnswers: QuestionAnswer[];
    initialAnswers: Record<string, QuestionAnswer[]>;
    serverErrors?: Record<string, string>;
    onBack: () => void;
    onDone: (answers: Record<string, QuestionAnswer[]>) => void;
}

interface StageForm {
    products: { questions: QuestionAnswer[] }[];
}

const pageFromErrors = (errors?: Record<string, string>): number => {
    const match = Object.keys(errors ?? {}).map(key => key.match(/^products\.(\d+)\./)).find(Boolean);
    return match ? Number(match[1]) : 0;
};

export const AttendeeQuestionsStage = ({
                                           slots,
                                           allQuestions,
                                           orderAnswers,
                                           initialAnswers,
                                           serverErrors,
                                           onBack,
                                           onDone,
                                       }: AttendeeQuestionsStageProps) => {
    const [page, setPage] = useState(() => pageFromErrors(serverErrors));
    const form = useForm<StageForm>({
        initialValues: {
            products: slots.map(slot => ({
                questions: slot.questions.map(question => ({
                    question_id: question.id as number,
                    response: initialAnswers[slot.key]?.find(a => a.question_id === question.id)?.response ?? {},
                })),
            })),
        },
    });

    useEffect(() => {
        if (serverErrors) form.setErrors(serverErrors);
    }, [serverErrors]);

    const slot = slots[page];
    const isLast = page === slots.length - 1;
    const current = page + 1;
    const total = slots.length;
    const questionsById = indexQuestions(allQuestions);
    const orderAnswerMap = answersByQuestionId(orderAnswers);

    const validatePage = (): boolean => {
        const errors: Record<string, string> = {};
        const attendeeAnswerMap = answersByQuestionId(form.values.products[page].questions);
        let hasIneligibleAnswer = false;

        form.values.products[page].questions.forEach((answer, index) => {
            const question = slot.questions[index];
            if (!isQuestionShown(question, questionsById, orderAnswerMap, attendeeAnswerMap, slot.productId)) {
                return;
            }
            if (question.required && !isQuestionAnswered(question, [answer])) {
                errors[`products.${page}.questions.${index}.response.answer`] = t`This field is required`;
            }
            if (isDisqualifyingAnswer(question, extractAnswer(answer.response))) {
                hasIneligibleAnswer = true;
            }
        });
        form.setErrors(errors);
        return Object.keys(errors).length === 0 && !hasIneligibleAnswer;
    };

    const next = () => {
        if (!validatePage()) return;
        if (!isLast) {
            setPage(page + 1);
            return;
        }
        onDone(Object.fromEntries(slots.map((s, index) => [s.key, form.values.products[index].questions])));
    };

    return (
        <div className={classes.stageCard}>
            <div className={classes.stageTotal}>
                <div className={classes.stageTotalLabel}>{t`Attendee ${current} of ${total}`}</div>
                <div className={classes.stageTotalValue} style={{fontSize: 22}}>{slot.productTitle}</div>
            </div>
            <CheckoutProductQuestions
                questions={slot.questions}
                form={form as any}
                product={{id: slot.productId} as any}
                index={page}
                allQuestions={allQuestions}
                orderAnswers={orderAnswerMap}
            />
            <Button size="lg" fullWidth mt="md" onClick={next} data-testid="box-office-attendee-next-button">
                {isLast ? t`Continue to payment` : t`Next attendee`}
            </Button>
            <Button variant="subtle" color="gray" leftSection={<IconArrowLeft size={16}/>}
                    onClick={() => page === 0 ? onBack() : setPage(page - 1)}>
                {page === 0 ? t`Back to sale` : t`Back`}
            </Button>
        </div>
    );
};
