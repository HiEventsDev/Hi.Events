import {t} from "@lingui/macro";
import {QuestionRequestData, QuestionType} from "../../../../types.ts";

export interface QuestionTemplate {
    id: string;
    label: string;
    values: Partial<QuestionRequestData>;
}

export const getQuestionTemplates = (): QuestionTemplate[] => [
    {
        id: 'dietary',
        label: t`Dietary requirements`,
        values: {
            type: QuestionType.RADIO,
            title: t`Dietary requirements`,
            options: [t`None`, t`Vegetarian`, t`Vegan`, t`Gluten-free`, t`Halal`, t`Kosher`, t`Other`],
        },
    },
    {
        id: 't-shirt',
        label: t`T-shirt size`,
        values: {
            type: QuestionType.DROPDOWN,
            title: t`T-shirt size`,
            options: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        },
    },
    {
        id: 'company',
        label: t`Company`,
        values: {type: QuestionType.SINGLE_LINE_TEXT, title: t`Company`},
    },
    {
        id: 'job-title',
        label: t`Job title`,
        values: {type: QuestionType.SINGLE_LINE_TEXT, title: t`Job title`},
    },
    {
        id: 'phone',
        label: t`Phone number`,
        values: {type: QuestionType.PHONE, title: t`Phone number`},
    },
    {
        id: 'accessibility',
        label: t`Accessibility needs`,
        values: {type: QuestionType.MULTI_LINE_TEXT, title: t`Do you have any accessibility needs?`},
    },
    {
        id: 'referral',
        label: t`How did you hear about us?`,
        values: {
            type: QuestionType.DROPDOWN,
            title: t`How did you hear about us?`,
            options: [t`Social media`, t`Search engine`, t`Friend or colleague`, t`Email newsletter`, t`Other`],
        },
    },
    {
        id: 'age-check',
        label: t`Age check (18+)`,
        values: {
            type: QuestionType.RADIO,
            title: t`Are you 18 or older?`,
            options: [t`Yes`, t`No`],
            required: true,
            disqualifying_options: [t`No`],
            disqualification_message: t`You must be 18 or older to attend this event.`,
        },
    },
    {
        id: 'code-of-conduct',
        label: t`Code of conduct`,
        values: {
            type: QuestionType.CHECKBOX,
            title: t`Code of conduct`,
            options: [t`I agree to follow the event code of conduct`],
            required: true,
        },
    },
];
