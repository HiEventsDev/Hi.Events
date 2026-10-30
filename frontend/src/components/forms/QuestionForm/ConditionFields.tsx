import {t} from "@lingui/macro";
import {Chip, Group, Radio, Select, Stack, Text} from "@mantine/core";
import {UseFormReturnType} from "@mantine/form";
import {IdParam, Question, QuestionBelongsToType} from "../../../types.ts";
import {isChoiceQuestionType} from "./questionTypes.ts";
import {QuestionFormValues} from "./questionFormValues.ts";
import classes from "./QuestionForm.module.scss";

interface ConditionFieldsProps {
    form: UseFormReturnType<QuestionFormValues>;
    questions: Question[];
    questionId?: IdParam;
}

const descendantIds = (questionId: IdParam | undefined, questions: Question[]): Set<number> => {
    const descendants = new Set<number>();
    if (questionId === undefined) {
        return descendants;
    }

    let parents = [Number(questionId)];
    while (parents.length > 0) {
        const children = questions
            .filter((question) => parents.includes(Number(question.condition_question_id)))
            .map((question) => Number(question.id))
            .filter((id) => !descendants.has(id) && id !== Number(questionId));
        children.forEach((id) => descendants.add(id));
        parents = children;
    }

    return descendants;
};

export const ConditionFields = ({form, questions, questionId}: ConditionFieldsProps) => {
    const excludedIds = descendantIds(questionId, questions);
    const selectedParentId = form.values.condition_question_id ? String(form.values.condition_question_id) : null;
    const candidateParents = questions.filter((question) =>
        isChoiceQuestionType(question.type)
        && Number(question.id) !== Number(questionId)
        && !excludedIds.has(Number(question.id))
        && (!question.is_hidden || String(question.id) === selectedParentId)
        && (form.values.belongs_to === QuestionBelongsToType.PRODUCT || question.belongs_to === QuestionBelongsToType.ORDER)
    );
    const parent = candidateParents.find((question) => String(question.id) === selectedParentId);
    const isConditional = selectedParentId !== null;

    if (candidateParents.length === 0 && !isConditional) {
        return (
            <Text size="xs" c="dimmed">
                {t`Add a question with options above this one to show it only for certain answers.`}
            </Text>
        );
    }

    return (
        <Stack gap="sm">
            <Radio.Group
                value={isConditional ? 'conditional' : 'always'}
                onChange={(value) => {
                    form.setFieldValue('condition_question_id', value === 'always' ? null : String(candidateParents[0]?.id ?? ''));
                    form.setFieldValue('condition_values', []);
                }}
            >
                <Stack gap="xs">
                    <Radio value="always" label={t`Always`}/>
                    <Radio value="conditional" label={t`Only when an earlier answer matches`}/>
                </Stack>
            </Radio.Group>

            {isConditional && (
                <div className={classes.conditionBuilder}>
                    <Select
                        label={t`When the answer to`}
                        value={selectedParentId}
                        onChange={(value) => {
                            form.setFieldValue('condition_question_id', value);
                            form.setFieldValue('condition_values', []);
                        }}
                        allowDeselect={false}
                        data={candidateParents.map((question) => ({value: String(question.id), label: question.title}))}
                        error={form.errors.condition_question_id}
                    />
                    {parent && (
                        <div>
                            <Text size="sm" fw={500} mb={6}>{t`is any of`}</Text>
                            <Chip.Group
                                multiple
                                value={form.values.condition_values ?? []}
                                onChange={(values) => form.setFieldValue('condition_values', values)}
                            >
                                <Group gap="xs">
                                    {(parent.options ?? []).map((option) => (
                                        <Chip key={option} value={option} size="sm" variant="outline">{option}</Chip>
                                    ))}
                                </Group>
                            </Chip.Group>
                            {form.errors.condition_values && (
                                <Text size="xs" c="red" mt={6}>{form.errors.condition_values}</Text>
                            )}
                        </div>
                    )}
                </div>
            )}
        </Stack>
    );
};
