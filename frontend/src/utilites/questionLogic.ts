import {Question, QuestionBelongsToType} from "../types.ts";

export type AnswerMap = Record<number, unknown>;

const MAX_CONDITION_DEPTH = 10;

export const extractAnswer = (response: unknown): unknown => {
    if (response && typeof response === 'object' && !Array.isArray(response) && 'answer' in response) {
        return (response as { answer: unknown }).answer;
    }

    return response;
};

export const answersByQuestionId = (entries: unknown): AnswerMap => {
    if (!Array.isArray(entries)) {
        return {};
    }

    return entries.reduce<AnswerMap>((answers, entry) => {
        const questionId = Number(entry?.question_id);
        if (Number.isFinite(questionId)) {
            answers[questionId] = extractAnswer(entry?.response);
        }
        return answers;
    }, {});
};

export const indexQuestions = (questions: Question[]): Map<number, Question> =>
    new Map(questions.map((question) => [Number(question.id), question]));

const selectedValues = (answer: unknown): string[] =>
    (Array.isArray(answer) ? answer : [answer])
        .filter((value) => typeof value === 'string' || typeof value === 'number')
        .map(String);

const appliesToProduct = (question: Question, productId?: number): boolean =>
    productId !== undefined && !!question.product_ids?.map(Number).includes(Number(productId));

export const isQuestionShown = (
    question: Question,
    questionsById: Map<number, Question>,
    orderAnswers: AnswerMap,
    attendeeAnswers: AnswerMap = {},
    productId?: number,
): boolean => {
    let current = question;

    for (let depth = 0; depth < MAX_CONDITION_DEPTH; depth++) {
        const parentId = current.condition_question_id;
        if (!parentId) {
            return true;
        }

        const parent = questionsById.get(Number(parentId));
        if (!parent || parent.is_hidden) {
            return false;
        }

        const parentIsAttendeeQuestion = parent.belongs_to === QuestionBelongsToType.PRODUCT;
        if (parentIsAttendeeQuestion && !appliesToProduct(parent, productId)) {
            return false;
        }

        const answer = parentIsAttendeeQuestion ? attendeeAnswers[Number(parent.id)] : orderAnswers[Number(parent.id)];
        const conditionValues = current.condition_values ?? [];
        if (!selectedValues(answer).some((value) => conditionValues.includes(value))) {
            return false;
        }

        current = parent;
    }

    return false;
};

export const isDisqualifyingAnswer = (question: Question, answer: unknown): boolean => {
    const disqualifyingOptions = question.disqualifying_options ?? [];

    return disqualifyingOptions.length > 0 && selectedValues(answer).some((value) => disqualifyingOptions.includes(value));
};

export const findDisqualifyingQuestion = (
    questions: Question[],
    questionsById: Map<number, Question>,
    orderAnswers: AnswerMap,
    attendeeAnswers: AnswerMap = {},
    productId?: number,
): Question | undefined => {
    const answerFor = (question: Question) => question.belongs_to === QuestionBelongsToType.PRODUCT
        ? attendeeAnswers[Number(question.id)]
        : orderAnswers[Number(question.id)];

    return questions.find((question) =>
        isDisqualifyingAnswer(question, answerFor(question))
        && isQuestionShown(question, questionsById, orderAnswers, attendeeAnswers, productId)
    );
};
