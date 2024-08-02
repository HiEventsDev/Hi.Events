import {Question, QuestionType, Ticket} from "../../../types.ts";
import {UseFormReturnType} from "@mantine/form";
import {Box, Checkbox, ComboboxItem, Group, NativeSelect, Radio, Select, Textarea, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import countries from "../../../../data/countries.json";
import {InputGroup} from "../InputGroup";
import classes from "./CheckoutQuestion.module.scss";
import {UserGeneratedContent} from "../UserGeneratedContent";
import {DatePicker} from "@mantine/dates";

interface CheckoutQuestionProps {
    questions: Question[],
    form: UseFormReturnType<any, any>;
}

interface QuestionInputProps {
    question: Partial<Question>,
    name: string,
    form: UseFormReturnType<any, any>,
}

interface CheckoutTicketQuestionProps {
    questions: Question[],
    form: UseFormReturnType<any, any>;
    ticket: Ticket,
    index: number,
}

const DropDownInput = ({question, name, form}: QuestionInputProps) => {
    const items: ComboboxItem[] = [];

    question.options?.map((option) => {
        items.push({
            value: option,
            label: option,
        })
    });
    return (
        <Select
            classNames={{
                description: classes.descriptionWithNoStyle,
            }}
            description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
            {...form.getInputProps(`${name}.answer`)}
            data={items}
            label={question.title}
            withAsterisk={question.required}
        />
    );
}

const MultiLineTextInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <>
            <Textarea
                classNames={{
                    description: classes.descriptionWithNoStyle,
                }}
                description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
                {...form.getInputProps(`${name}.answer`)} withAsterisk={question.required}
                label={question.title}/>
        </>
    );
}

const DateInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <>
            <TextInput withAsterisk={question.required}
                       type="date"
                       {...form.getInputProps(`${name}.answer`)}
                       label={question.title}
            />
        </>
    );
}

const SingleLineTextInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <>
            <TextInput
                classNames={{
                    description: classes.descriptionWithNoStyle,
                }}
                {...form.getInputProps(`${name}.answer`)}
                withAsterisk={question.required}
                label={question.title}
                description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
            />
        </>
    );
}

const RadioInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <Radio.Group
            classNames={{
                description: classes.descriptionWithNoStyle,
            }}
            withAsterisk={question.required}
            {...form.getInputProps(`${name}.answer`)}
            label={question.title}
            description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
        >
            <Group mt="xs">
                {question.options?.map((option, index) => {
                    return (
                        <Radio
                            key={`${question.id}-radio-${index}`}
                            label={option}
                            value={option}
                        />
                    )
                })}
            </Group>
        </Radio.Group>
    )
}

const CheckBoxInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <Checkbox.Group
            classNames={{
                description: classes.descriptionWithNoStyle,
            }}
            withAsterisk={question.required}
            {...form.getInputProps(`${name}.answer`)}
            label={question.title}
            description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
        >
            <Group mt="xs">
                {question.options?.map((option, index) => {
                    return (
                        <Checkbox
                            key={`${question.id}-checkbox-${index}`}
                            label={option}
                            value={option}
                        />
                    )
                })}
            </Group>
        </Checkbox.Group>
    );
}

const AddressInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <>
            <h4>{question.title}</h4>
            <div className={classes.description} dangerouslySetInnerHTML={{__html: question.description || ''}}/>

            <TextInput withAsterisk={question.required}
                       {...form.getInputProps(`${name}.address_line_1`)}
                       label={t`Address line 1`}/>
            <TextInput mt={20}
                       {...form.getInputProps(`${name}.address_line_2`)}
                       label={t`Address line 2`}/>
            <InputGroup>
                <TextInput withAsterisk={question.required}
                           {...form.getInputProps(`${name}.city`)} label={t`City`}/>
                <TextInput withAsterisk={question.required}
                           {...form.getInputProps(`${name}.state_or_region`)}
                           label={t`State or Region`}/>
            </InputGroup>
            <InputGroup>
                <TextInput withAsterisk={question.required}
                           {...form.getInputProps(`${name}.zip_or_postal_code`)}
                           label={t`Zip or Postal Code`}/>
                <NativeSelect withAsterisk={question.required}
                              data={countries}
                              {...form.getInputProps(`${name}.country`)}
                              label={t`Country`}/>
            </InputGroup>
        </>
    );
}

export const QuestionInput = ({question, name, form}: QuestionInputProps) => {
    let input;
    switch (question.type) {
        case QuestionType.ADDRESS:
            input = <AddressInput question={question} name={name} form={form}/>
            break;
        case QuestionType.CHECKBOX:
            input = <CheckBoxInput question={question} name={name} form={form}/>
            break;
        case QuestionType.MULTI_LINE_TEXT:
            input = <MultiLineTextInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.RADIO:
            input = <RadioInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.DROPDOWN:
            input = <DropDownInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.SINGLE_LINE_TEXT:
            input = <SingleLineTextInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.DATE:
            input = <DateInput question={question} name={name} form={form}/>;
            break;
    }

    return (
        <Box mt={20}>
            {input}
        </Box>
    )
};

export const CheckoutOrderQuestions = ({questions, form}: CheckoutQuestionProps) => {
    let questionIndex = 0;
    return (
        <>
            {questions.map((question, index) => {
                const name = `order.questions.${questionIndex++}.response`;
                return <QuestionInput key={`${index}-question`} question={question} name={name} form={form}/>
            })}
        </>
    )
}

export const CheckoutTicketQuestions = ({
                                            questions,
                                            form,
                                            ticket,
                                            index: attendeeIndex
                                        }: CheckoutTicketQuestionProps) => {
    let questionIndex = 0;
    return (
        <>
            {questions.map((question, index) => {
                if (!question.ticket_ids?.includes(Number(ticket.id))) {
                    return;
                }

                const name = `attendees.${attendeeIndex}.questions.${questionIndex++}.response`;
                return <QuestionInput key={`${index}-attendee`} question={question} name={name} form={form}/>
            })}
        </>
    )
}

