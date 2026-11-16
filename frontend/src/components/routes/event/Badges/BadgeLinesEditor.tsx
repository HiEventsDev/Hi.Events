import {ActionIcon, Button, Group, Menu, Select, Text, TextInput} from "@mantine/core";
import {WriteAccess} from "../../../common/WriteAccess";
import {IconArrowDown, IconArrowUp, IconDots, IconPlus, IconSparkles, IconX} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BadgeDesignLine, BadgeFieldSource, BadgeTextSize, Question} from "../../../../types.ts";
import {getBadgeFieldSourceLabel, getBadgeTextSizeLabel, MAX_BADGE_LINES} from "../../../../utilites/badges.ts";
import classes from "./BadgeDesigner.module.scss";

interface BadgeLinesEditorProps {
    lines: BadgeDesignLine[];
    questions: Question[];
    onChange: (lines: BadgeDesignLine[]) => void;
    disabled?: boolean;
    suggestion?: {
        label: string;
        onAdd: () => void;
        loading: boolean;
    };
}

const QUESTION_PREFIX = 'QUESTION:';
const TEXT_SIZES: BadgeTextSize[] = ['XL', 'L', 'M', 'S'];
const NEXT_SIZE: Record<number, BadgeTextSize> = {0: 'XL', 1: 'L', 2: 'M'};

const encodeSource = (line: BadgeDesignLine): string => (
    line.source === 'QUESTION' ? `${QUESTION_PREFIX}${line.question_id}` : line.source
);

export const BadgeLinesEditor = ({lines, questions, onChange, disabled, suggestion}: BadgeLinesEditorProps) => {
    const attendeeSources: BadgeFieldSource[] = ['FIRST_NAME', 'LAST_NAME', 'FULL_NAME', 'TICKET_TYPE', 'OCCURRENCE_DATE'];
    const bindableQuestions = questions.filter((question) => question.type !== 'ADDRESS');

    const sourceOptions = [
        {
            group: t`Attendee`,
            items: attendeeSources.map((source) => ({value: source, label: getBadgeFieldSourceLabel(source)})),
        },
        ...(bindableQuestions.length ? [{
            group: t`Registration questions`,
            items: bindableQuestions.map((question) => ({
                value: `${QUESTION_PREFIX}${question.id}`,
                label: question.title,
            })),
        }] : []),
        {
            group: t`Other`,
            items: [{value: 'CUSTOM_TEXT', label: getBadgeFieldSourceLabel('CUSTOM_TEXT')}],
        },
    ];

    const updateLine = (index: number, patch: Partial<BadgeDesignLine>) => {
        onChange(lines.map((line, i) => (i === index ? {...line, ...patch} : line)));
    };

    const setSource = (index: number, value: string | null) => {
        if (!value) {
            return;
        }
        if (value.startsWith(QUESTION_PREFIX)) {
            updateLine(index, {source: 'QUESTION', question_id: Number(value.slice(QUESTION_PREFIX.length)), text: null});
            return;
        }
        updateLine(index, {source: value as BadgeFieldSource, question_id: null});
    };

    const move = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= lines.length) {
            return;
        }
        const next = [...lines];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    const addLine = () => {
        const used = new Set(lines.filter((line) => line.source === 'QUESTION').map((line) => line.question_id));
        const unusedQuestion = bindableQuestions.find((question) => !used.has(question.id));
        onChange([
            ...lines,
            unusedQuestion
                ? {source: 'QUESTION', question_id: unusedQuestion.id, size: NEXT_SIZE[lines.length] ?? 'S'}
                : {source: 'CUSTOM_TEXT', text: '', size: NEXT_SIZE[lines.length] ?? 'S'},
        ]);
    };

    return (
        <div className={classes.lines}>
            {lines.map((line, index) => (
                <div key={index} className={classes.lineRow} data-testid={`badge-line-${index}`}>
                    <span className={classes.lineIndex}>{index + 1}</span>
                    <div className={classes.lineFields}>
                        <Group gap={6} wrap="nowrap" className={classes.lineControls}>
                            <Select
                                className={classes.lineSource}
                                size="xs"
                                data={sourceOptions}
                                value={encodeSource(line)}
                                onChange={(value) => setSource(index, value)}
                                allowDeselect={false}
                                searchable
                                disabled={disabled}
                                aria-label={t`What this line shows`}
                                comboboxProps={{withinPortal: true}}
                                data-testid={`badge-line-${index}-source`}
                            />
                            <Select
                                className={classes.lineSize}
                                size="xs"
                                data={TEXT_SIZES.map((size) => ({value: size, label: getBadgeTextSizeLabel(size)}))}
                                value={line.size}
                                onChange={(value) => value && updateLine(index, {size: value as BadgeTextSize})}
                                allowDeselect={false}
                                disabled={disabled}
                                aria-label={t`Text size`}
                                comboboxProps={{withinPortal: true}}
                            />
                        </Group>
                        {line.source === 'CUSTOM_TEXT' && (
                            <TextInput
                                size="xs"
                                maxLength={80}
                                placeholder={t`For example: Ask me about volunteering`}
                                value={line.text ?? ''}
                                onChange={(event) => updateLine(index, {text: event.currentTarget.value})}
                                disabled={disabled}
                                aria-label={t`Custom text`}
                                data-testid={`badge-line-${index}-text`}
                            />
                        )}
                    </div>
                    <Menu position="bottom-end" withinPortal shadow="md">
                        <Menu.Target>
                            <ActionIcon
                                variant="subtle"
                                color="gray"
                                size="input-xs"
                                className={classes.lineActions}
                                disabled={disabled}
                                aria-label={t`Line options`}
                            >
                                <IconDots size={16}/>
                            </ActionIcon>
                        </Menu.Target>
                        <Menu.Dropdown>
                            <Menu.Item leftSection={<IconArrowUp size={14}/>} disabled={index === 0} onClick={() => move(index, -1)}>
                                {t`Move up`}
                            </Menu.Item>
                            <Menu.Item leftSection={<IconArrowDown size={14}/>} disabled={index === lines.length - 1} onClick={() => move(index, 1)}>
                                {t`Move down`}
                            </Menu.Item>
                            <Menu.Item
                                color="red"
                                leftSection={<IconX size={14}/>}
                                disabled={lines.length === 1}
                                onClick={() => onChange(lines.filter((_, i) => i !== index))}
                            >
                                {t`Remove line`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </div>
            ))}

            <Group gap="xs" mt={4}>
                <Button
                    size="compact-sm"
                    variant="light"
                    leftSection={<IconPlus size={14}/>}
                    onClick={addLine}
                    disabled={disabled || lines.length >= MAX_BADGE_LINES}
                    data-testid="badge-add-line-button"
                >
                    {t`Add line`}
                </Button>
                {lines.length >= MAX_BADGE_LINES && (
                    <Text size="xs" c="dimmed">{t`Badges fit up to 5 lines`}</Text>
                )}
            </Group>

            {suggestion && (
                <WriteAccess>
                    <div className={classes.suggestion}>
                        <IconSparkles size={16} className={classes.suggestionIcon}/>
                        <div className={classes.suggestionText}>
                            <Text size="sm" fw={500}>{suggestion.label}</Text>
                            <Text size="xs" c="dimmed">
                                {t`Adds optional questions to checkout for every ticket, then puts the answers on the badge.`}
                            </Text>
                        </div>
                        <Button size="compact-sm" variant="default" onClick={suggestion.onAdd} loading={suggestion.loading}
                                disabled={disabled} data-testid="badge-add-company-questions-button">
                            {t`Add`}
                        </Button>
                    </div>
                </WriteAccess>
            )}
        </div>
    );
};
