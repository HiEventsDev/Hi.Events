import {t} from "@lingui/macro";
import {
    IconAlignBoxLeftTop,
    IconCalendar,
    IconCircleDot,
    IconForms,
    IconListCheck,
    IconMapPin,
    IconPhone,
    IconSelector,
    IconSquareCheck,
    TablerIcon,
} from "@tabler/icons-react";
import {QuestionType} from "../../../types.ts";

export interface QuestionTypeOption {
    type: QuestionType;
    label: string;
    Icon: TablerIcon;
}

export const getQuestionTypeOptions = (): QuestionTypeOption[] => [
    {type: QuestionType.SINGLE_LINE_TEXT, label: t`Short answer`, Icon: IconForms},
    {type: QuestionType.MULTI_LINE_TEXT, label: t`Paragraph`, Icon: IconAlignBoxLeftTop},
    {type: QuestionType.RADIO, label: t`Single choice`, Icon: IconCircleDot},
    {type: QuestionType.CHECKBOX, label: t`Checkboxes`, Icon: IconSquareCheck},
    {type: QuestionType.DROPDOWN, label: t`Dropdown`, Icon: IconSelector},
    {type: QuestionType.MULTI_SELECT_DROPDOWN, label: t`Multi-select dropdown`, Icon: IconListCheck},
    {type: QuestionType.DATE, label: t`Date`, Icon: IconCalendar},
    {type: QuestionType.PHONE, label: t`Phone number`, Icon: IconPhone},
    {type: QuestionType.ADDRESS, label: t`Address`, Icon: IconMapPin},
];

export const getQuestionTypeOption = (type?: string): QuestionTypeOption | undefined =>
    getQuestionTypeOptions().find((option) => option.type === type);

const CHOICE_QUESTION_TYPES: string[] = [
    QuestionType.RADIO,
    QuestionType.CHECKBOX,
    QuestionType.DROPDOWN,
    QuestionType.MULTI_SELECT_DROPDOWN,
];

export const isChoiceQuestionType = (type?: string): boolean => !!type && CHOICE_QUESTION_TYPES.includes(type);
