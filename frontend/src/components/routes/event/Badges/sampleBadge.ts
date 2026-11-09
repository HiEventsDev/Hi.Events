import {t} from "@lingui/macro";
import {Badge, BadgeDesign, BadgeLine, Product, Question} from "../../../../types.ts";

export const COMPANY_QUESTION_PATTERN = /\b(company|organi[sz]ation|employer|business|firm|firma|empresa|entreprise|soci[ée]t[ée]|unternehmen|azienda|bedrijf|organisatie)\b/i;
export const JOB_TITLE_QUESTION_PATTERN = /(job\s*title|\brole\b|\bposition\b|\bdesignation\b|\boccupation\b|\bberuf\b|\bfunktion\b|\bposte\b|\bcargo\b|\bpuesto\b|\bruolo\b|\bfunctie\b)/i;
const PRONOUNS_QUESTION_PATTERN = /pronoun|pronom|pronomen/i;

interface SampleBadgeOptions {
    design: BadgeDesign;
    questions: Question[];
    product?: Product;
    firstName?: string;
    lastName?: string;
    longName?: boolean;
    seatLabel?: string | null;
}

const sampleAnswer = (question: Question | undefined): string => {
    if (!question) {
        return '';
    }
    if (COMPANY_QUESTION_PATTERN.test(question.title)) {
        return t`Northwind Labs`;
    }
    if (JOB_TITLE_QUESTION_PATTERN.test(question.title)) {
        return t`Head of Partnerships`;
    }
    if (PRONOUNS_QUESTION_PATTERN.test(question.title)) {
        return t`she/her`;
    }
    if (question.options?.length) {
        return question.options[0];
    }
    return question.title;
};

export const buildSampleBadge = ({
                                     design,
                                     questions,
                                     product,
                                     firstName,
                                     lastName,
                                     longName,
                                     seatLabel = null,
                                 }: SampleBadgeOptions): Badge => {
    const first = longName ? 'Maximiliana' : (firstName || 'Alex');
    const last = longName ? 'Vandenberg-Oyelaran' : (lastName || 'Morgan');
    const ticketTitle = product?.title ?? t`General Admission`;
    const configuredRibbon = design.ribbons.find((ribbon) => ribbon.product_id === product?.id);

    const lines: BadgeLine[] = design.lines.flatMap((line): BadgeLine[] => {
        const text = {
            FIRST_NAME: first,
            LAST_NAME: last,
            FULL_NAME: `${first} ${last}`,
            TICKET_TYPE: ticketTitle,
            QUESTION: sampleAnswer(questions.find((question) => question.id === line.question_id)),
            OCCURRENCE_DATE: new Date().toISOString(),
            CUSTOM_TEXT: line.text ?? '',
        }[line.source];

        return text ? [{source: line.source, size: line.size, text}] : [];
    });

    return {
        attendee_id: 0,
        attendee_public_id: 'A-SAMPLE01',
        order_id: 0,
        product_id: product?.id ?? 0,
        event_occurrence_id: null,
        status: 'ACTIVE',
        first_name: first,
        last_name: last,
        lines,
        ribbon: design.show_ribbon
            ? {label: configuredRibbon?.label || ticketTitle, color: configuredRibbon?.color ?? design.accent_color}
            : null,
        seat_label: design.show_seat ? seatLabel : null,
        badge_printed_at: null,
        badge_print_count: 0,
        print_status: 'NOT_PRINTED',
    };
};
