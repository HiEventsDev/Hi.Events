import { CustomSelect, ItemProps } from "../../common/CustomSelect";
import { t, Trans } from "@lingui/macro";
import { ContactAttributeDefinition, ProductCategory, QuestionBelongsToType, QuestionType } from "../../../types.ts";
import { ActionIcon, Button, Checkbox, Group, Select, Switch, TextInput, Tooltip } from "@mantine/core";
import {
  IconAlignBoxLeftTop,
  IconCalendar,
  IconCircleCheck,
  IconForms,
  IconInfoCircle,
  IconLock,
  IconLockOpen,
  IconMapPin,
  IconReceipt,
  IconSelector,
  IconSquareCheck,
  IconTicket,
  IconTrash,
  IconUser
} from "@tabler/icons-react";
import { UseFormReturnType } from "@mantine/form";
import { Card } from "../../common/Card";
import classes from "./QuestionForm.module.scss";
import { Editor } from "../../common/Editor";
import { useEffect, useState } from "react";
import { ProductSelector } from "../../common/ProductSelector";
import { useGetContactAttributeDefinitions } from "../../../queries/useGetContactAttributeDefinitions.ts";
import { confirmationDialog } from "../../../utilites/confirmationDialog.tsx";

const NEW_QUESTION_SENTINEL = '__new__';

export const slugifyToAttributeName = (input: string): string => {
  const base = (input ?? '')
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
  return base || 'attribute';
};

export const questionTypeFromDefinitionType = (definitionType: ContactAttributeDefinition['type']): string => {
  switch (definitionType) {
    case 'select':
      return QuestionType.DROPDOWN.toString();
    case 'multi_select':
      return QuestionType.CHECKBOX.toString();
    default:
      return QuestionType.SINGLE_LINE_TEXT.toString();
  }
};

export const definitionTypeFromQuestionType = (questionType: string): ContactAttributeDefinition['type'] => {
  if (questionType === QuestionType.CHECKBOX.toString()) return 'multi_select';
  if ([QuestionType.RADIO.toString(), QuestionType.DROPDOWN.toString()].includes(questionType)) return 'select';
  return 'text';
};

const isDefinitionCompatibleWithQuestionType = (
  definitionType: ContactAttributeDefinition['type'],
  questionType: string,
): boolean => {
  switch (definitionType) {
    case 'text':
      return ![QuestionType.CHECKBOX.toString(), QuestionType.RADIO.toString(), QuestionType.DROPDOWN.toString()].includes(questionType);
    case 'select':
      return [QuestionType.RADIO.toString(), QuestionType.DROPDOWN.toString()].includes(questionType);
    case 'multi_select':
      return questionType === QuestionType.CHECKBOX.toString();
    default:
      return false;
  }
};

const Options = ({ form, locked, onRequestUnlock }: { form: UseFormReturnType<any>; locked: boolean; onRequestUnlock: () => void }) => {
  return (
    <Card className={locked ? classes.lockedField : undefined}>
      <Group justify="space-between" align="center" mb="xs">
        <h3 className={classes.optionsHeading}><Trans>Options</Trans></h3>
        {locked && (
          <Tooltip label={t`Unlock to edit options for this event only`}>
            <ActionIcon variant="subtle" onClick={onRequestUnlock} aria-label={t`Unlock options`}>
              <IconLock size={16} />
            </ActionIcon>
          </Tooltip>
        )}
      </Group>
      {form.values.options.length === 0 && (
        <div className={classes.noOptionsMessage}>
          <Trans>Please add at least one option</Trans>
        </div>
      )}

      {form.values.options.map((_: any, index: number) => {
        const i = index + 1;
        return (
          <Group wrap={'nowrap'} justify={'space-between'} className={classes.optionRow} key={index}>
            <div className={classes.optionInputWrap}>
              <TextInput
                mt={0}
                mb={0}
                {...form.getInputProps(`options.${index}`)}
                placeholder={t`Option ${i}`}
                required
                disabled={locked}
                className={classes.optionInput}
              />
            </div>
            <div className={classes.optionButton}>
              <Button
                size='xs'
                variant="outline"
                disabled={locked}
                onClick={() => form.setFieldValue('options', form.values.options.filter((_: any, i: number) => i !== index))}
              >
                <IconTrash size={16} />
              </Button>
            </div>
          </Group>
        );
      })}

      <Button
        variant="outline"
        disabled={locked}
        onClick={() => form.setFieldValue('options', [...form.values.options, ''])}
        size="xs"
      >
        {t`Add Option`}
      </Button>
    </Card>
  )
};

const LockableLabel = ({ label, locked, onToggle, showIcon }: {
  label: string;
  locked: boolean;
  onToggle: () => void;
  showIcon: boolean;
}) => (
  <Group gap={6} align="center" mb={4}>
    <span>{label}</span>
    {showIcon && (
      <Tooltip label={locked ? t`Unlock to edit (this will change the field for this event only)` : t`This field is linked to a reusable attribute`}>
        <ActionIcon variant="subtle" size="xs" onClick={onToggle} aria-label={locked ? t`Unlock field` : t`Re-lock field`}>
          {locked ? <IconLock size={14} /> : <IconLockOpen size={14} />}
        </ActionIcon>
      </Tooltip>
    )}
  </Group>
);

interface QuestionFormProps {
  form: UseFormReturnType<any>;
  productCategories?: ProductCategory[];
  isEditMode?: boolean;
}

export const QuestionForm = ({ form, productCategories, isEditMode = false }: QuestionFormProps) => {
  const [showDescription, setShowDescription] = useState(false);
  const definitionsQuery = useGetContactAttributeDefinitions();
  const definitions = (definitionsQuery.data?.data ?? []).filter((d) => d.is_active);

  const reusableSelection: string = form.values.__reusable_selection ?? NEW_QUESTION_SENTINEL;
  const linkedDefinitionId: number | null = reusableSelection !== NEW_QUESTION_SENTINEL ? Number(reusableSelection) : null;
  const linkedDefinition = linkedDefinitionId != null ? definitions.find((d) => d.id === linkedDefinitionId) : undefined;
  const isLinked = !!linkedDefinition;

  const [unlockedFields, setUnlockedFields] = useState<{ title: boolean; type: boolean; options: boolean }>({
    title: false,
    type: false,
    options: false,
  });

  const titleLocked = isLinked && !unlockedFields.title;
  const typeLocked = isLinked && !unlockedFields.type;
  const optionsLocked = isLinked && !unlockedFields.options;

  useEffect(() => {
    if (reusableSelection === NEW_QUESTION_SENTINEL) {
      form.setFieldValue('contact_attribute_definition_id', null);
      setUnlockedFields({ title: false, type: false, options: false });
      return;
    }
    if (!linkedDefinition) return;

    const derivedType = questionTypeFromDefinitionType(linkedDefinition.type);
    form.setFieldValue('title', linkedDefinition.label);
    form.setFieldValue('type', derivedType);
    form.setFieldValue('contact_attribute_definition_id', linkedDefinition.id ?? null);
    if (linkedDefinition.options && linkedDefinition.options.length > 0) {
      form.setFieldValue('options', [...linkedDefinition.options]);
    }
    setUnlockedFields({ title: false, type: false, options: false });
  }, [reusableSelection]);

  const requestUnlock = (field: 'title' | 'type' | 'options') => {
    if (unlockedFields[field]) {
      setUnlockedFields((prev) => ({ ...prev, [field]: false }));
      return;
    }
    confirmationDialog(
      t`Unlock this field and override the reusable attribute for this event only?`,
      () => setUnlockedFields((prev) => ({ ...prev, [field]: true })),
    );
  };

  const newQuestionLabel = form.values.__make_reusable
    ? t`New Question (reusable on future events)`
    : t`New Question (this event only)`;
  const reusableOptions = [
    { value: NEW_QUESTION_SENTINEL, label: newQuestionLabel },
    ...definitions.map((d) => ({ value: String(d.id), label: d.label })),
  ];

  const belongToOptions: ItemProps[] = [
    {
      icon: <IconReceipt />,
      label: t`Ask once per order`,
      value: QuestionBelongsToType.ORDER,
      description: t`A single question per order. E.g, What is your shipping address?`,
    },
    {
      icon: <IconUser />,
      label: t`Ask once per product`,
      value: QuestionBelongsToType.PRODUCT,
      description: t`A single question per product. E.g, What is your t-shirt size?`,
    },
  ];

  const questionTypeOptions: ItemProps[] = [
    {
      icon: <IconForms />,
      label: t`Single line text box`,
      value: QuestionType.SINGLE_LINE_TEXT,
      description: t`A single line text input`,
    },
    {
      icon: <IconAlignBoxLeftTop />,
      label: t`Multi line text box`,
      value: QuestionType.MULTI_LINE_TEXT,
      description: t`A multi line text input`,
    },
    {
      icon: <IconSquareCheck />,
      label: t`Checkboxes`,
      value: QuestionType.CHECKBOX,
      description: t`Checkbox options allow multiple selections`,
    },
    {
      icon: <IconCircleCheck />,
      label: t`Radio Option`,
      value: QuestionType.RADIO,
      description: t`A Radio option has multiple options but only one can be selected.`,
    },
    {
      icon: <IconSelector />,
      label: t`Dropdown selection`,
      value: QuestionType.DROPDOWN,
      description: t`A Dropdown input allows only one selection`,
    },
    {
      icon: <IconMapPin />,
      label: t`Address`,
      value: QuestionType.ADDRESS,
      description: t`Shows common address fields, including country`,
    },
    {
      icon: <IconCalendar />,
      label: t`Date`,
      value: QuestionType.DATE,
      description: t`A date input. Perfect for asking for a date of birth etc.`,
    }
  ];
  const multiAnswerQuestionTypes = [
    QuestionType.CHECKBOX.toString(),
    QuestionType.RADIO.toString(),
    QuestionType.DROPDOWN.toString(),
  ];

  const filteredQuestionTypeOptions = typeLocked && linkedDefinition
    ? questionTypeOptions.filter((opt) => isDefinitionCompatibleWithQuestionType(linkedDefinition.type, String(opt.value)))
    : questionTypeOptions;

  return (
    <>
      {!isEditMode && (
        <Select
          label={t`Re-use Existing Question`}
          description={t`Answers will update the attendee's contact profile, making them reusable in future`}
          data={reusableOptions}
          value={reusableSelection}
          allowDeselect={false}
          onChange={(value) => {
            form.setFieldValue('__reusable_selection', value ?? NEW_QUESTION_SENTINEL);
          }}
        />
      )}

      {reusableSelection === NEW_QUESTION_SENTINEL && (
        <Checkbox
          mt="sm"
          mb="lg"
          label={(
            <Group gap={6} align="center">
              <span>{t`Make this question reusable for future events`}</span>
              <Tooltip label={t`Question will be added to the account library for use on future events`} withArrow multiline w={260}>
                <IconInfoCircle size={14} style={{ opacity: 0.6, cursor: 'help' }} />
              </Tooltip>
            </Group>
          )}
          {...form.getInputProps('__make_reusable', { type: 'checkbox' })}
        />
      )}

      <CustomSelect
        optionList={belongToOptions}
        label={t`Who should be asked this question?`}
        required
        form={form}
        name="belongs_to"
      />

      {form.values.belongs_to === QuestionBelongsToType.PRODUCT && (
        <ProductSelector
          label={t`What products does this code apply to?`}
          placeholder="Select products"
          icon={<IconTicket size="1rem" />}
          productCategories={productCategories ?? []}
          form={form}
          productFieldName="product_ids"
        />
      )}

      <div className={typeLocked ? classes.lockedField : undefined}>
        <LockableLabel
          label={t`What type of question is this?`}
          locked={typeLocked}
          onToggle={() => requestUnlock('type')}
          showIcon={isLinked}
        />
        <CustomSelect
          optionList={filteredQuestionTypeOptions}
          label=""
          required
          form={form}
          name="type"
          disabled={typeLocked}
        />
      </div>

      <div className={titleLocked ? classes.lockedField : undefined}>
        <LockableLabel
          label={t`Question Title`}
          locked={titleLocked}
          onToggle={() => requestUnlock('title')}
          showIcon={isLinked}
        />
        <TextInput
          {...form.getInputProps('title')}
          placeholder={t`What time will you be arriving?`}
          required
          disabled={titleLocked}
        />
      </div>

      {(showDescription || form.values.description) ? (
        <Editor
          maxLength={10000}
          editorType={'simple'}
          error={form.errors.description as string}
          label={t`Question Description`}
          description={t`Provide additional context or instructions for this question. Use this field to add terms
                                and conditions, guidelines, or any important information that attendees need to know before answering.`}
          value={form.values.description}
          onChange={(value: string) => form.setFieldValue('description', value)}
        />
      ) : (
        <Button
          variant="transparent"
          ml={0}
          pl={0}
          mb={10}
          onClick={() => setShowDescription(true)}
        >
          {t`Add description`}
        </Button>
      )}

      {multiAnswerQuestionTypes.includes(form.values.type) && (
        <Options form={form} locked={optionsLocked} onRequestUnlock={() => requestUnlock('options')} />
      )}

      <Switch
        mt={20}
        {...form.getInputProps('required', { type: 'checkbox' })}
        description={t`Mandatory questions must be answered before the customer can checkout.`}
        label={t`Make this question mandatory`}
      />

      <Switch
        mt={20}
        {...form.getInputProps('is_hidden', { type: 'checkbox' })}
        description={t`Hidden questions are only visible to the event organizer and not to the customer.`}
        label={t`Hide this question`}
      />
    </>
  )
}
