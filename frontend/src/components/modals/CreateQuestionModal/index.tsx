import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {GenericModalProps} from "../../../types.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventQuestions} from "../../../queries/useGetEventQuestions.ts";
import {useCreateQuestion} from "../../../mutations/useCreateQuestion.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {QuestionForm} from "../../forms/QuestionForm";
import {QuestionDrawer} from "../../forms/QuestionForm/QuestionDrawer.tsx";
import {formValuesToRequest, QuestionFormValues, questionFormValidation} from "../../forms/QuestionForm/questionFormValues.ts";

interface CreateQuestionModalProps extends GenericModalProps {
    initialValues: QuestionFormValues;
    heading?: string;
}

export const CreateQuestionModal = ({onClose, initialValues, heading}: CreateQuestionModalProps) => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const questionsQuery = useGetEventQuestions(eventId);
    const mutation = useCreateQuestion();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<QuestionFormValues>({
        initialValues,
        validate: questionFormValidation,
    });

    const handleSubmit = (values: QuestionFormValues) => {
        mutation.mutate({eventId, questionData: formValuesToRequest(values)}, {
            onSuccess: () => {
                showSuccess(t`Question created`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    };

    return (
        <QuestionDrawer
            title={heading ?? t`Create Question`}
            form={form}
            submitLabel={t`Create Question`}
            submitLoading={mutation.isPending}
            onSubmit={handleSubmit}
            onClose={onClose}
        >
            <QuestionForm
                form={form}
                productCategories={eventQuery.data?.product_categories}
                questions={questionsQuery.data ?? []}
            />
        </QuestionDrawer>
    );
};
