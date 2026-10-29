import {useEffect} from "react";
import {Alert, Button, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {Question} from "../../../../../types.ts";
import {CartBuyer} from "../../../hooks/useBoxOfficeCart.ts";
import {CheckoutOrderQuestions} from "../../../../../components/common/CheckoutQuestion";
import {isQuestionAnswered} from "../../../utilites/boxOfficeQuestions.ts";
import {Sheet} from "./Sheet.tsx";

interface BuyerSheetProps {
    opened: boolean;
    onClose: () => void;
    buyer: CartBuyer;
    questions: Question[];
    questionAnswers: { question_id: number; response: any }[];
    requireAnswers?: boolean;
    serverErrors?: Record<string, string>;
    onSave: (buyer: CartBuyer, answers: { question_id: number; response: any }[]) => void;
}

interface BuyerForm extends CartBuyer {
    order: { questions: { question_id: number; response: any }[] };
}

export const BuyerSheet = ({opened, onClose, buyer, questions, questionAnswers, requireAnswers, serverErrors, onSave}: BuyerSheetProps) => {
    const form = useForm<BuyerForm>({
        initialValues: {
            ...buyer,
            order: {
                questions: questions.map(question => ({
                    question_id: question.id as number,
                    response: questionAnswers.find(a => a.question_id === question.id)?.response ?? {},
                })),
            },
        },
        validate: (values) => {
            const errors: Record<string, string> = {};
            values.order.questions.forEach((answer, index) => {
                const question = questions.find(q => q.id === answer.question_id);
                if (question?.required && !isQuestionAnswered(question, [answer])) {
                    errors[`order.questions.${index}.response.answer`] = t`This field is required`;
                }
            });
            return errors;
        },
    });

    useEffect(() => {
        if (opened) {
            form.setValues({
                ...buyer,
                order: {
                    questions: questions.map(question => ({
                        question_id: question.id as number,
                        response: questionAnswers.find(a => a.question_id === question.id)?.response ?? {},
                    })),
                },
            });
            if (serverErrors) form.setErrors(serverErrors);
        }
    }, [opened]);

    return (
        <Sheet opened={opened} onClose={onClose} title={t`Buyer details`}>
            <form onSubmit={form.onSubmit((values) => onSave(
                {first_name: values.first_name, last_name: values.last_name, email: values.email},
                values.order.questions,
            ))}>
                <TextInput {...form.getInputProps('first_name')} label={t`First name`} autoComplete="off" size="md"/>
                <TextInput {...form.getInputProps('last_name')} label={t`Last name`} autoComplete="off" size="md" mt="sm"/>
                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Email`}
                    description={t`Add an email to send the tickets`}
                    type="email"
                    inputMode="email"
                    autoComplete="off"
                    size="md"
                    mt="sm"
                />
                {requireAnswers && (
                    <Alert color="orange" variant="light" mt="md">{t`Answer the required questions to continue the sale.`}</Alert>
                )}
                {questions.length > 0 && (
                    <div style={{marginTop: 16}}>
                        <CheckoutOrderQuestions questions={questions} form={form as any}/>
                    </div>
                )}
                <Button type="submit" fullWidth size="md" mt="md" data-testid="box-office-buyer-save-button">{t`Save`}</Button>
            </form>
        </Sheet>
    );
};
