import {t} from "@lingui/macro";
import {Question, QuestionBelongsToType, QuestionRequestData, QuestionType} from "../../../types.ts";
import {isChoiceQuestionType} from "./questionTypes.ts";

export interface QuestionFormValues extends QuestionRequestData {
    restricts_eligibility: boolean;
}

export const getDefaultQuestionValues = (
    belongsTo: QuestionBelongsToType,
    overrides: Partial<QuestionRequestData> = {},
): QuestionFormValues => ({
    title: '',
    description: '',
    type: QuestionType.SINGLE_LINE_TEXT,
    required: false,
    options: [],
    product_ids: [],
    applies_to_all_tickets: belongsTo === QuestionBelongsToType.PRODUCT,
    belongs_to: belongsTo,
    is_hidden: false,
    condition_question_id: null,
    condition_values: [],
    disqualifying_options: [],
    disqualification_message: '',
    option_renames: [],
    ...overrides,
    restricts_eligibility: (overrides.disqualifying_options ?? []).length > 0,
});

export const questionToFormValues = (question: Question): QuestionFormValues => ({
    title: question.title,
    description: question.description ?? '',
    type: question.type,
    required: question.required,
    options: question.options ?? [],
    product_ids: question.product_ids?.map(String) ?? [],
    applies_to_all_tickets: question.applies_to_all_tickets ?? false,
    belongs_to: question.belongs_to,
    is_hidden: question.is_hidden,
    condition_question_id: question.condition_question_id ? String(question.condition_question_id) : null,
    condition_values: question.condition_values ?? [],
    disqualifying_options: question.disqualifying_options ?? [],
    disqualification_message: question.disqualification_message ?? '',
    option_renames: [],
    restricts_eligibility: (question.disqualifying_options ?? []).length > 0,
});

const cleanOptions = (options: string[]): string[] => {
    const trimmed = options.map((option) => option.trim()).filter((option) => option !== '');

    return Array.from(new Set(trimmed));
};

export const formValuesToRequest = ({restricts_eligibility, ...values}: QuestionFormValues): QuestionRequestData => {
    const isAttendeeQuestion = values.belongs_to === QuestionBelongsToType.PRODUCT;
    const appliesToAllTickets = isAttendeeQuestion && !!values.applies_to_all_tickets;
    const isChoice = isChoiceQuestionType(values.type);
    const options = isChoice ? cleanOptions(values.options) : [];
    const disqualifyingOptions = isChoice && restricts_eligibility
        ? (values.disqualifying_options ?? []).map((option) => option.trim()).filter((option) => options.includes(option))
        : [];
    const conditionQuestionId = values.condition_question_id ? Number(values.condition_question_id) : null;

    return {
        ...values,
        title: values.title.trim(),
        options,
        applies_to_all_tickets: appliesToAllTickets,
        product_ids: isAttendeeQuestion && !appliesToAllTickets ? values.product_ids : [],
        condition_question_id: conditionQuestionId,
        condition_values: conditionQuestionId ? values.condition_values ?? [] : [],
        disqualifying_options: disqualifyingOptions,
        disqualification_message: disqualifyingOptions.length > 0 ? (values.disqualification_message ?? '').trim() : '',
        option_renames: isChoice
            ? (values.option_renames ?? []).filter(({from, to}) => from.trim() !== '' && to.trim() !== '' && from !== to.trim())
                .map(({from, to}) => ({from, to: to.trim()}))
            : [],
    };
};

const blocksRegistration = (values: QuestionFormValues): boolean =>
    isChoiceQuestionType(values.type) && values.restricts_eligibility;

export const questionFormValidation = {
    title: (value: string) => value.trim() === '' ? t`Enter a question` : null,
    options: (value: string[], values: QuestionFormValues) => isChoiceQuestionType(values.type) && cleanOptions(value).length === 0
        ? t`Add at least one option`
        : null,
    product_ids: (value: string[] | undefined, values: QuestionFormValues) => values.belongs_to === QuestionBelongsToType.PRODUCT
    && !values.applies_to_all_tickets
    && (value ?? []).length === 0
        ? t`Choose at least one ticket or product`
        : null,
    condition_values: (value: string[] | undefined, values: QuestionFormValues) => values.condition_question_id && (value ?? []).length === 0
        ? t`Choose at least one answer`
        : null,
    disqualifying_options: (value: string[] | undefined, values: QuestionFormValues) => {
        if (!blocksRegistration(values)) {
            return null;
        }

        const options = cleanOptions(values.options);
        const blocked = (value ?? []).filter((option) => options.includes(option.trim()));

        if (blocked.length === 0) {
            return t`Choose at least one answer that blocks registration`;
        }

        return blocked.length >= options.length ? t`At least one answer must let people register` : null;
    },
    disqualification_message: (value: string | undefined, values: QuestionFormValues) => blocksRegistration(values)
    && (value ?? '').trim() === ''
        ? t`Enter a message for people who aren't eligible`
        : null,
};
