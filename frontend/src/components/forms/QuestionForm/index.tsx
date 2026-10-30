import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Checkbox, Group, Radio, SegmentedControl, Select, Stack, Switch, Text, Textarea, TextInput} from "@mantine/core";
import {useForm, UseFormReturnType} from "@mantine/form";
import {IconPlus} from "@tabler/icons-react";
import {IdParam, ProductCategory, ProductType, Question, QuestionBelongsToType} from "../../../types.ts";
import {Editor} from "../../common/Editor";
import {ProductSelector} from "../../common/ProductSelector";
import {SideDrawerSection} from "../../common/SideDrawer";
import {QuestionInput} from "../../common/CheckoutQuestion";
import {extractAnswer, isDisqualifyingAnswer} from "../../../utilites/questionLogic.ts";
import {OptionsEditor} from "./OptionsEditor.tsx";
import {ConditionFields} from "./ConditionFields.tsx";
import {getQuestionTypeOption, getQuestionTypeOptions, isChoiceQuestionType} from "./questionTypes.ts";
import {QuestionFormValues} from "./questionFormValues.ts";
import classes from "./QuestionForm.module.scss";

interface QuestionFormProps {
    form: UseFormReturnType<QuestionFormValues>;
    productCategories?: ProductCategory[];
    questions?: Question[];
    questionId?: IdParam;
}

const QuestionPreview = ({values}: { values: QuestionFormValues }) => {
    const previewForm = useForm({initialValues: {preview: {}}});

    return (
        <div className={classes.preview}>
            <QuestionInput
                question={{
                    type: values.type,
                    title: values.title.trim() || t`Your question`,
                    description: values.description,
                    required: values.required,
                    options: values.options.filter((option) => option.trim() !== ''),
                }}
                name="preview"
                form={previewForm}
            />
            {values.restricts_eligibility
                && isDisqualifyingAnswer({disqualifying_options: values.disqualifying_options} as Question, extractAnswer(previewForm.values.preview)) && (
                <Text size="xs" c="red" mt="xs">
                    {values.disqualification_message || t`This answer means you can't register for this event.`}
                </Text>
            )}
        </div>
    );
};

export const QuestionForm = ({form, productCategories, questions = [], questionId}: QuestionFormProps) => {
    const [showDescription, setShowDescription] = useState(!!form.values.description);
    const typeOptions = getQuestionTypeOptions();
    const SelectedTypeIcon = getQuestionTypeOption(form.values.type)?.Icon;
    const isAttendeeQuestion = form.values.belongs_to === QuestionBelongsToType.PRODUCT;
    const blocksRegistration = isChoiceQuestionType(form.values.type) && form.values.restricts_eligibility;
    const answerOptions = Array.from(new Set(form.values.options.filter((option) => option.trim() !== '')));

    const handleTypeChange = (type: string | null) => {
        if (!type) {
            return;
        }

        form.setFieldValue('type', type);

        if (isChoiceQuestionType(type) && form.values.options.length === 0) {
            form.setFieldValue('options', ['', '']);
        }
    };

    const handleBelongsToChange = (belongsTo: string) => {
        const parent = questions.find((question) => String(question.id) === String(form.values.condition_question_id));

        form.setFieldValue('belongs_to', belongsTo);

        if (belongsTo === QuestionBelongsToType.ORDER && parent?.belongs_to === QuestionBelongsToType.PRODUCT) {
            form.setFieldValue('condition_question_id', null);
            form.setFieldValue('condition_values', []);
        }

        if (belongsTo === QuestionBelongsToType.PRODUCT && (form.values.product_ids ?? []).length === 0) {
            form.setFieldValue('applies_to_all_tickets', true);
        }
    };

    return (
        <>
            <SideDrawerSection title={t`Question`}>
                <Stack gap="md" className={classes.fields}>
                    <TextInput
                        {...form.getInputProps('title')}
                        label={t`Question`}
                        placeholder={t`What's your job title?`}
                        required
                        data-testid="question-title-input"
                    />

                    <Select
                        label={t`Answer type`}
                        value={form.values.type}
                        onChange={handleTypeChange}
                        allowDeselect={false}
                        data={typeOptions.map((option) => ({value: option.type, label: option.label}))}
                        leftSection={SelectedTypeIcon && <SelectedTypeIcon size={16} stroke={1.5}/>}
                        error={form.errors.type}
                        renderOption={({option}) => {
                            const Icon = getQuestionTypeOption(option.value)?.Icon;

                            return (
                                <Group gap="xs" wrap="nowrap">
                                    {Icon && <Icon size={16} stroke={1.5}/>}
                                    <span>{option.label}</span>
                                </Group>
                            );
                        }}
                    />

                    {showDescription ? (
                        <Editor
                            maxLength={10000}
                            editorType="simple"
                            error={form.errors.description as string}
                            label={t`Description`}
                            description={t`Shown under the question. Use it for instructions, terms or extra context.`}
                            value={form.values.description ?? ''}
                            onChange={(value: string) => form.setFieldValue('description', value)}
                        />
                    ) : (
                        <div>
                            <Button
                                variant="subtle"
                                size="xs"
                                leftSection={<IconPlus size={14}/>}
                                onClick={() => setShowDescription(true)}
                            >
                                {t`Add description`}
                            </Button>
                        </div>
                    )}
                </Stack>
            </SideDrawerSection>

            {isChoiceQuestionType(form.values.type) && (
                <SideDrawerSection title={t`Options`}>
                    <OptionsEditor form={form}/>
                </SideDrawerSection>
            )}

            {isChoiceQuestionType(form.values.type) && (
                <SideDrawerSection title={t`Eligibility`}>
                    <Stack gap="md" className={classes.fields}>
                        <Switch
                            checked={form.values.restricts_eligibility}
                            onChange={(event) => form.setFieldValue('restricts_eligibility', event.currentTarget.checked)}
                            label={t`Block registration for some answers`}
                            description={t`People who pick one of these answers can't complete their order. Useful for age limits or member-only events.`}
                        />

                        {blocksRegistration && (
                            <div className={classes.eligibilityPanel}>
                                {answerOptions.length === 0 ? (
                                    <Text size="xs" c="dimmed">
                                        {t`Add options above, then choose which answers block registration.`}
                                    </Text>
                                ) : (
                                    <Checkbox.Group
                                        label={t`Answers that block registration`}
                                        value={form.values.disqualifying_options ?? []}
                                        onChange={(value) => form.setFieldValue('disqualifying_options', value)}
                                        error={form.errors.disqualifying_options}
                                    >
                                        <Stack gap={6} mt={6}>
                                            {answerOptions.map((option) => (
                                                <Checkbox
                                                    key={option}
                                                    value={option}
                                                    label={option}
                                                />
                                            ))}
                                        </Stack>
                                    </Checkbox.Group>
                                )}
                                <Textarea
                                    {...form.getInputProps('disqualification_message')}
                                    label={t`Message for people who aren't eligible`}
                                    description={t`Shown under the question when someone picks one of these answers.`}
                                    placeholder={t`This event is open to healthcare professionals only.`}
                                    maxLength={500}
                                    autosize
                                    minRows={2}
                                    required
                                    data-testid="question-ineligible-message-input"
                                />
                            </div>
                        )}
                    </Stack>
                </SideDrawerSection>
            )}

            <SideDrawerSection title={t`Who answers`}>
                <Stack gap="md" className={classes.fields}>
                    <SegmentedControl
                        fullWidth
                        value={form.values.belongs_to}
                        onChange={handleBelongsToChange}
                        data={[
                            {value: QuestionBelongsToType.ORDER, label: t`Once per order`},
                            {value: QuestionBelongsToType.PRODUCT, label: t`Each attendee`},
                        ]}
                    />
                    {form.errors.belongs_to && (
                        <Text size="xs" c="red">{form.errors.belongs_to}</Text>
                    )}
                    <Text size="xs" c="dimmed">
                        {isAttendeeQuestion
                            ? t`Asked separately for every ticket in the order, e.g. t-shirt size or dietary needs.`
                            : t`Asked once of the person placing the order, e.g. how they heard about the event.`}
                    </Text>

                    {isAttendeeQuestion && (
                        <Radio.Group
                            value={form.values.applies_to_all_tickets ? 'all' : 'specific'}
                            onChange={(value) => form.setFieldValue('applies_to_all_tickets', value === 'all')}
                            label={t`Ask for`}
                        >
                            <Stack gap="xs" mt={6}>
                                <Radio
                                    value="all"
                                    label={t`All tickets`}
                                    description={t`Includes tickets you add later.`}
                                />
                                <Radio
                                    value="specific"
                                    label={t`Specific tickets and products`}
                                />
                            </Stack>
                        </Radio.Group>
                    )}

                    {isAttendeeQuestion && !form.values.applies_to_all_tickets && (
                        <ProductSelector
                            label={t`Tickets and products`}
                            placeholder={t`Choose tickets or products`}
                            productCategories={productCategories ?? []}
                            includedProductTypes={[ProductType.Ticket, ProductType.General]}
                            form={form}
                            productFieldName="product_ids"
                        />
                    )}
                </Stack>
            </SideDrawerSection>

            {!form.values.is_hidden && (
                <SideDrawerSection title={t`Show this question`}>
                    <ConditionFields form={form} questions={questions} questionId={questionId}/>
                </SideDrawerSection>
            )}

            <SideDrawerSection title={t`Settings`}>
                <Stack gap="md" className={classes.fields}>
                    <Switch
                        {...form.getInputProps('required', {type: 'checkbox'})}
                        checked={form.values.required || blocksRegistration}
                        disabled={blocksRegistration}
                        label={t`Required`}
                        description={blocksRegistration
                            ? t`Questions with ineligible answers are always required.`
                            : form.values.condition_question_id
                                ? t`Only required when the question is shown.`
                                : t`Buyers can't continue until they answer.`}
                    />
                    <Switch
                        {...form.getInputProps('is_hidden', {type: 'checkbox'})}
                        label={t`Hide at checkout`}
                        description={t`Only your team can see and fill in this question.`}
                    />
                </Stack>
            </SideDrawerSection>

            <SideDrawerSection title={t`Preview`}>
                <QuestionPreview key={form.values.type} values={form.values}/>
            </SideDrawerSection>
        </>
    );
};
