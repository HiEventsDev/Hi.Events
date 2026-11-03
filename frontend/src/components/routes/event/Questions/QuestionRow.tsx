import {CSSProperties, ReactNode} from "react";
import {plural, t} from "@lingui/macro";
import {ActionIcon, Badge, Text, Tooltip, UnstyledButton} from "@mantine/core";
import {
    IconCopy,
    IconCornerDownRight,
    IconDotsVertical,
    IconEye,
    IconEyeOff,
    IconGripVertical,
    IconPencil,
    IconTrash,
} from "@tabler/icons-react";
import {useSortable} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {Question, QuestionBelongsToType} from "../../../../types.ts";
import {ActionMenu} from "../../../common/ActionMenu";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {getQuestionTypeOption, isChoiceQuestionType} from "../../../forms/QuestionForm/questionTypes.ts";
import classes from "./QuestionsBuilder.module.scss";

export interface QuestionRowHandlers {
    onEdit: (question: Question) => void;
    onDuplicate: (question: Question) => void;
    onAddFollowUp: (question: Question) => void;
    onToggleHidden: (question: Question) => void;
    onDelete: (question: Question) => void;
}

interface QuestionRowProps extends QuestionRowHandlers {
    question: Question;
    depth: number;
    parent?: Question;
    isNestedUnderParent: boolean;
    canAddFollowUp: boolean;
    productTitles: Map<number, string>;
    dragHandle?: ReactNode;
}

export interface FollowUpRow {
    question: Question;
    depth: number;
    parent?: Question;
    canAddFollowUp: boolean;
}

interface SortableQuestionGroupProps extends QuestionRowHandlers {
    root: Question;
    rootParent?: Question;
    canAddFollowUp: boolean;
    followUps: FollowUpRow[];
    productTitles: Map<number, string>;
}

const MAX_LISTED_PRODUCTS = 2;

const getScopeLabel = (question: Question, productTitles: Map<number, string>): string => {
    if (question.applies_to_all_tickets) {
        return t`All tickets`;
    }

    const titles = (question.product_ids ?? [])
        .map((id) => productTitles.get(Number(id)))
        .filter((title): title is string => !!title);

    if (titles.length === 0) {
        return t`No tickets selected`;
    }

    const listed = titles.slice(0, MAX_LISTED_PRODUCTS).join(', ');
    const remaining = titles.length - MAX_LISTED_PRODUCTS;

    return remaining > 0 ? t`${listed} +${remaining} more` : listed;
};

const QuestionRow = ({
                         question,
                         depth,
                         parent,
                         isNestedUnderParent,
                         canAddFollowUp,
                         productTitles,
                         dragHandle,
                         onEdit,
                         onDuplicate,
                         onAddFollowUp,
                         onToggleHidden,
                         onDelete,
                     }: QuestionRowProps) => {
    const {isReadOnly} = useAccess();
    const typeOption = getQuestionTypeOption(question.type);
    const TypeIcon = typeOption?.Icon;
    const optionCount = question.options?.length ?? 0;
    const conditionValues = (question.condition_values ?? []).join(', ');
    const ineligibleValues = (question.disqualifying_options ?? []).join(', ');
    const meta = [
        typeOption?.label,
        isChoiceQuestionType(question.type) ? plural(optionCount, {one: '# option', other: '# options'}) : null,
        question.belongs_to === QuestionBelongsToType.PRODUCT && depth === 0 ? getScopeLabel(question, productTitles) : null,
    ].filter(Boolean).join(' · ');

    return (
        <div
            className={classes.row}
            data-hidden={question.is_hidden || undefined}
            data-follow-up={depth > 0 || undefined}
            style={{'--question-depth': depth} as CSSProperties}
            data-testid="question-row"
        >
            {dragHandle ?? <span className={classes.dragSpacer}/>}
            {depth > 0 && <IconCornerDownRight size={14} className={classes.followUpIcon} aria-hidden/>}

            <UnstyledButton
                className={classes.rowMain}
                onClick={() => onEdit(question)}
                disabled={isReadOnly}
            >
                <span className={classes.rowIcon}>
                    {TypeIcon && <TypeIcon size={18} stroke={1.5}/>}
                </span>
                <span className={classes.rowText}>
                    <span className={classes.rowTitleLine}>
                        <Text component="span" size="sm" fw={500} className={classes.rowTitle}>
                            {question.title}
                        </Text>
                        {question.required && (
                            <Badge size="xs" variant="light" color="gray" className={classes.requiredBadge}>{t`Required`}</Badge>
                        )}
                        {question.is_hidden && (
                            <Tooltip label={t`Hidden at checkout. Only your team can see and fill in this question.`}>
                                <IconEyeOff size={14} className={classes.hiddenIcon} aria-label={t`Hidden at checkout`}/>
                            </Tooltip>
                        )}
                    </span>
                    <span className={classes.rowBadges}>
                        {question.condition_question_id && (
                            <Badge size="xs" variant="light" color="blue" tt="none" className={classes.rowBadge}>
                                {isNestedUnderParent || !parent
                                    ? t`When: ${conditionValues}`
                                    : t`When “${parent.title}”: ${conditionValues}`}
                            </Badge>
                        )}
                        {ineligibleValues && (
                            <Badge size="xs" variant="light" color="red" tt="none" className={classes.rowBadge}>
                                {t`Ineligible: ${ineligibleValues}`}
                            </Badge>
                        )}
                        <Text component="span" size="xs" c="dimmed" className={classes.rowMeta}>{meta}</Text>
                    </span>
                </span>
            </UnstyledButton>

            <ActionMenu
                target={(
                    <ActionIcon variant="subtle" color="gray" aria-label={t`Question actions`}>
                        <IconDotsVertical size={16}/>
                    </ActionIcon>
                )}
                dataTestId="question-row-menu"
                itemsGroups={[
                    {
                        label: t`Manage`,
                        items: [
                            {
                                label: t`Edit`,
                                icon: <IconPencil size={14}/>,
                                onClick: () => onEdit(question),
                                requiresEdit: true,
                            },
                            {
                                label: t`Add follow-up question`,
                                icon: <IconCornerDownRight size={14}/>,
                                onClick: () => onAddFollowUp(question),
                                visible: canAddFollowUp,
                                requiresEdit: true,
                                dataTestId: 'question-add-follow-up-menu-item',
                            },
                            {
                                label: t`Duplicate`,
                                icon: <IconCopy size={14}/>,
                                onClick: () => onDuplicate(question),
                                requiresEdit: true,
                            },
                            {
                                label: question.is_hidden ? t`Show at checkout` : t`Hide at checkout`,
                                icon: question.is_hidden ? <IconEye size={14}/> : <IconEyeOff size={14}/>,
                                onClick: () => onToggleHidden(question),
                                requiresEdit: true,
                            },
                        ],
                    },
                    {
                        label: t`Danger zone`,
                        showDividerAbove: true,
                        items: [
                            {
                                label: t`Delete`,
                                icon: <IconTrash size={14}/>,
                                color: 'red',
                                onClick: () => onDelete(question),
                                requiresEdit: true,
                            },
                        ],
                    },
                ]}
            />
        </div>
    );
};

export const SortableQuestionGroup = ({
                                          root,
                                          rootParent,
                                          canAddFollowUp,
                                          followUps,
                                          productTitles,
                                          ...handlers
                                      }: SortableQuestionGroupProps) => {
    const {isReadOnly} = useAccess();
    const {attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging} = useSortable({
        id: Number(root.id),
        disabled: isReadOnly,
    });

    return (
        <div
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={classes.group}
            data-dragging={isDragging || undefined}
        >
            <QuestionRow
                question={root}
                depth={0}
                parent={rootParent}
                isNestedUnderParent={false}
                canAddFollowUp={canAddFollowUp}
                productTitles={productTitles}
                dragHandle={isReadOnly ? undefined : (
                    <button
                        type="button"
                        ref={setActivatorNodeRef}
                        className={classes.dragHandle}
                        aria-label={t`Reorder ${root.title}`}
                        {...attributes}
                        {...listeners}
                    >
                        <IconGripVertical size={16} stroke={1.5}/>
                    </button>
                )}
                {...handlers}
            />
            {followUps.map((followUp) => (
                <QuestionRow
                    key={followUp.question.id}
                    question={followUp.question}
                    depth={followUp.depth}
                    parent={followUp.parent}
                    isNestedUnderParent
                    canAddFollowUp={followUp.canAddFollowUp}
                    productTitles={productTitles}
                    {...handlers}
                />
            ))}
        </div>
    );
};
