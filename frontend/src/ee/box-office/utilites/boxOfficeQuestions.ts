import {BoxOfficeProduct, Question} from "../../../types.ts";
import {CartLine} from "../hooks/useBoxOfficeCart.ts";

export type QuestionAnswer = { question_id: number; response: any };

const ADDRESS_REQUIRED_FIELDS = ['address_line_1', 'city', 'state_or_region', 'zip_or_postal_code', 'country'];

const hasValue = (value: unknown): boolean => {
    if (Array.isArray(value)) return value.length > 0;
    if (typeof value === 'string') return value.trim().length > 0;
    return value !== null && value !== undefined;
};

export const isQuestionAnswered = (question: Question, answers: QuestionAnswer[]): boolean => {
    const response = answers.find(answer => answer.question_id === question.id)?.response;
    if (!response) return false;
    if (question.type === 'ADDRESS') {
        return ADDRESS_REQUIRED_FIELDS.every(field => hasValue(response[field]));
    }
    return hasValue(response.answer);
};

export const unansweredRequiredQuestions = (questions: Question[], answers: QuestionAnswer[]): Question[] =>
    questions.filter(question => question.required && !isQuestionAnswered(question, answers));

export interface AttendeeSlot {
    key: string;
    productId: number;
    priceId: number;
    productTitle: string;
    questions: Question[];
}

export const slotKey = (priceId: number, unit: number): string => `${priceId}:${unit}`;

export const buildAttendeeSlots = (
    lines: CartLine[],
    products: Map<number, { product: BoxOfficeProduct }>,
    productQuestions: Question[],
): AttendeeSlot[] =>
    lines.flatMap(line => {
        const questions = productQuestions.filter(question => question.product_ids?.includes(line.productId));
        if (questions.length === 0) return [];
        const title = products.get(line.priceId)?.product.title ?? '';
        return Array.from({length: line.quantity}, (_, unit) => ({
            key: slotKey(line.priceId, unit),
            productId: line.productId,
            priceId: line.priceId,
            productTitle: title,
            questions,
        }));
    });
