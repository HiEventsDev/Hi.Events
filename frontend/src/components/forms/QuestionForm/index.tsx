import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {t, Trans} from "@lingui/macro";
import {QuestionBelongsToType, QuestionType, Ticket} from "../../../types.ts";
import {Button, Group, MultiSelect, Switch, TextInput} from "@mantine/core";
import {
    IconAlignBoxLeftTop,
    IconCalendar,
    IconCircleCheck,
    IconForms,
    IconMapPin,
    IconReceipt,
    IconSelector,
    IconSquareCheck,
    IconTicket,
    IconTrash,
    IconUser
} from "@tabler/icons-react";
import {UseFormReturnType} from "@mantine/form";
import {Card} from "../../common/Card";
import classes from "./QuestionForm.module.scss";
import {Editor} from "../../common/Editor";
import {useState} from "react";

const Options = ({form}: { form: UseFormReturnType<any> }) => {
    return (
        <Card>
            <h3 className={classes.optionsHeading}><Trans>Options</Trans></h3>
            {form.values.options.length === 0 && (
                <div className={classes.noOptionsMessage}>
                    <Trans>Please add at least one option</Trans>
                </div>
            )}

            {form.values.options.map((_: any, index: number) => {
                const i = index + 1;
                return (
                    <Group wrap={'nowrap'} justify={'space-between'} className={classes.optionRow}>
                        <div className={classes.optionInputWrap}>
                            <TextInput
                                key={index}
                                mt={0}
                                mb={0}
                                {...form.getInputProps(`options.${index}`)}
                                placeholder={t`Option ${i}`}
                                required
                                className={classes.optionInput}
                            />
                        </div>
                        <div className={classes.optionButton}>
                            <Button
                                size='xs'
                                variant="outline"
                                onClick={() => form.setFieldValue('options', form.values.options.filter((_: any, i: number) => i !== index))}
                            >
                                <IconTrash size={16}/>
                            </Button>
                        </div>
                    </Group>
                );
            })}

            <Button
                variant="outline"
                onClick={() => form.setFieldValue('options', [...form.values.options, ''])}
                size="xs"
            >
                {t`Add Option`}
            </Button>
        </Card>
    )
};

interface QuestionFormProps {
    form: UseFormReturnType<any>;
    tickets?: Ticket[];
}

export const QuestionForm = ({form, tickets}: QuestionFormProps) => {
    const [showDescription, setShowDescription] = useState(false);

    const belongToOptions: ItemProps[] = [
        {
            icon: <IconReceipt/>,
            label: t`Ask once per order`,
            value: QuestionBelongsToType.ORDER,
            description: t`A single question per order. E.g, What is your company name?`,
        },
        {
            icon: <IconUser/>,
            label: t`Ask once per attendee`,
            value: QuestionBelongsToType.TICKET,
            description: t`A single question per attendee. E.g, What is your preferred meal?`,
        },
    ];

    const questionTypeOptions: ItemProps[] = [
        {
            icon: <IconForms/>,
            label: t`Single line text box`,
            value: QuestionType.SINGLE_LINE_TEXT,
            description: t`A single line text input`,
        },
        {
            icon: <IconAlignBoxLeftTop/>,
            label: t`Multi line text box`,
            value: QuestionType.MULTI_LINE_TEXT,
            description: t`A multi line text input`,
        },
        {
            icon: <IconSquareCheck/>,
            label: t`Checkboxes`,
            value: QuestionType.CHECKBOX,
            description: t`Checkbox options allow multiple selections`,
        },
        {
            icon: <IconCircleCheck/>,
            label: t`Radio Option`,
            value: QuestionType.RADIO,
            description: t`A Radio option has multiple options but only one can be selected.`,
        },
        {
            icon: <IconSelector/>,
            label: t`Dropdown selection`,
            value: QuestionType.DROPDOWN,
            description: t`A Dropdown input allows only one selection`,
        },
        {
            icon: <IconMapPin/>,
            label: t`Address`,
            value: QuestionType.ADDRESS,
            description: t`Shows common address fields, including country`,
        },
        {
            icon: <IconCalendar/>,
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

    return (
        <>
            <CustomSelect
                optionList={belongToOptions}
                label={t`Who should be asked this question?`}
                required
                form={form}
                name="belongs_to"
            />

            {form.values.belongs_to === QuestionBelongsToType.TICKET && (
                <MultiSelect
                    mt={20}
                    label={t`What tickets should this question be apply to?`}
                    multiple
                    placeholder={t`Select tickets`}
                    data={tickets?.map(ticket => {
                        return {
                            value: String(ticket.id),
                            label: ticket.title,
                        }
                    })}
                    leftSection={<IconTicket size="1rem"/>}
                    {...form.getInputProps('ticket_ids')}
                />
            )}

            <CustomSelect
                optionList={questionTypeOptions}
                label={t`What type of question is this?`}
                required
                form={form}
                name="type"
            />

            <TextInput
                mt={20}
                {...form.getInputProps('title')}
                label={t`Question Title`}
                placeholder={t`What time will you be arriving?`}
                required
            />

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

            {multiAnswerQuestionTypes.includes(form.values.type) && <Options form={form}/>}

            <Switch
                mt={20}
                {...form.getInputProps('required', {type: 'checkbox'})}
                description={t`Mandatory questions must be answered before the customer can checkout.`}
                label={t`Make this question mandatory`}
            />

            <Switch
                mt={20}
                {...form.getInputProps('is_hidden', {type: 'checkbox'})}
                description={t`Hidden questions are only visible to the event organizer and not to the customer.`}
                label={t`Hide this question`}
            />
        </>
    )
}
