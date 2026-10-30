import {Product, Question, QuestionType} from "../../../types.ts";
import {UseFormReturnType} from "@mantine/form";
import {Box, Checkbox, ComboboxItem, Group, MultiSelect, NativeSelect, Radio, Select, Textarea, TextInput} from "@mantine/core";
import {IconCircleX} from "@tabler/icons-react";
import {ReactNode} from "react";
import {t} from "@lingui/macro";
import countries from "../../../../data/countries.json";
import {InputGroup} from "../InputGroup";
import classes from "./CheckoutQuestion.module.scss";
import {UserGeneratedContent} from "../UserGeneratedContent";
import {
    AnswerMap,
    answersByQuestionId,
    indexQuestions,
    isDisqualifyingAnswer,
    isQuestionShown,
} from "../../../utilites/questionLogic.ts";

interface CheckoutQuestionProps {
    questions: Question[],
    form: UseFormReturnType<any, any>;
    allQuestions?: Question[];
}

interface QuestionInputProps {
    question: Partial<Question>,
    name: string,
    form: UseFormReturnType<any, any>,
}

interface CheckoutProductQuestionProps {
    questions: Question[],
    form: UseFormReturnType<any, any>;
    product: Product,
    index: number,
    allQuestions?: Question[];
    orderAnswers?: AnswerMap;
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

const MultiSelectDropDownInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <MultiSelect
            classNames={{
                description: classes.descriptionWithNoStyle,
            }}
            description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
            {...form.getInputProps(`${name}.answer`)}
            data={question.options ?? []}
            label={question.title}
            withAsterisk={question.required}
        />
    );
}

const PhoneInput = ({question, name, form}: QuestionInputProps) => {
    return (
        <TextInput
            classNames={{
                description: classes.descriptionWithNoStyle,
            }}
            type="tel"
            autoComplete="tel"
            {...form.getInputProps(`${name}.answer`)}
            withAsterisk={question.required}
            label={question.title}
            description={(<UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
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
                       description={(
                           <UserGeneratedContent dangerouslySetInnerHTML={{__html: question.description || ''}}/>)}
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
            <UserGeneratedContent className={classes.description}
                                  dangerouslySetInnerHTML={{__html: question.description || ''}}/>

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
        case QuestionType.MULTI_SELECT_DROPDOWN:
            input = <MultiSelectDropDownInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.DATE:
            input = <DateInput question={question} name={name} form={form}/>;
            break;
        case QuestionType.PHONE:
            input = <PhoneInput question={question} name={name} form={form}/>;
            break;
    }

    return (
        <Box mt={20}>
            {input}
        </Box>
    )
};

export const LockedFields = ({locked, children}: { locked: boolean, children: ReactNode }) => {
    if (!locked) {
        return <>{children}</>;
    }

    return (
        <fieldset disabled className={classes.lockedFields}>
            {children}
        </fieldset>
    );
};

export const EligibilityNotice = ({question, answer}: { question: Partial<Question>, answer: unknown }) => {
    if (!isDisqualifyingAnswer(question as Question, answer)) {
        return null;
    }

    return (
        <div className={classes.eligibilityNotice} role="alert" data-testid="question-ineligible-notice">
            <IconCircleX size={20} className={classes.eligibilityNoticeIcon} aria-hidden/>
            <div>
                <p className={classes.eligibilityNoticeTitle}>{t`You can't register for this event`}</p>
                <p className={classes.eligibilityNoticeText}>
                    {question.disqualification_message || t`This answer means you can't register for this event.`}
                </p>
            </div>
        </div>
    );
};

export const CheckoutOrderQuestions = ({questions, form, allQuestions = questions}: CheckoutQuestionProps) => {
    const questionsById = indexQuestions(allQuestions);
    const orderAnswers = answersByQuestionId(form.values.order?.questions);
    let isAfterIneligibleAnswer = false;

    return (
        <>
            {questions.map((question, index) => {
                if (!isQuestionShown(question, questionsById, orderAnswers)) {
                    return null;
                }

                const answer = orderAnswers[Number(question.id)];
                const locked = isAfterIneligibleAnswer;
                isAfterIneligibleAnswer = isAfterIneligibleAnswer || isDisqualifyingAnswer(question, answer);

                return (
                    <LockedFields key={`${question.id}-question`} locked={locked}>
                        <QuestionInput question={question} name={`order.questions.${index}.response`} form={form}/>
                        {!locked && <EligibilityNotice question={question} answer={answer}/>}
                    </LockedFields>
                );
            })}
        </>
    )
}

export const CheckoutProductQuestions = ({
                                             questions,
                                             form,
                                             product,
                                             index: productIndex,
                                             allQuestions = questions,
                                             orderAnswers,
                                         }: CheckoutProductQuestionProps) => {
    const questionsById = indexQuestions(allQuestions);
    const attendeeAnswers = answersByQuestionId(form.values.products?.[productIndex]?.questions);
    const resolvedOrderAnswers = orderAnswers ?? answersByQuestionId(form.values.order?.questions);
    let questionIndex = 0;
    let isAfterIneligibleAnswer = false;

    return (
        <>
            {questions.map((question) => {
                if (!question.product_ids?.includes(Number(product.id))) {
                    return null;
                }

                const name = `products.${productIndex}.questions.${questionIndex++}.response`;

                if (!isQuestionShown(question, questionsById, resolvedOrderAnswers, attendeeAnswers, Number(product.id))) {
                    return null;
                }

                const answer = attendeeAnswers[Number(question.id)];
                const locked = isAfterIneligibleAnswer;
                isAfterIneligibleAnswer = isAfterIneligibleAnswer || isDisqualifyingAnswer(question, answer);

                return (
                    <LockedFields key={`${question.id}-product`} locked={locked}>
                        <QuestionInput question={question} name={name} form={form}/>
                        {!locked && <EligibilityNotice question={question} answer={answer}/>}
                    </LockedFields>
                );
            })}
        </>
    )
}
