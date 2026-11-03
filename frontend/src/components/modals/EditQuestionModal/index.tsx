import {useEffect, useState} from "react";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {GenericModalProps, IdParam, QuestionBelongsToType} from "../../../types.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventQuestions} from "../../../queries/useGetEventQuestions.ts";
import {useGetQuestion} from "../../../queries/useGetQuestion.ts";
import {useEditQuestion} from "../../../mutations/useEditQuestion.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {QuestionForm} from "../../forms/QuestionForm";
import {QuestionDrawer} from "../../forms/QuestionForm/QuestionDrawer.tsx";
import {
    formValuesToRequest,
    QuestionFormValues,
    getDefaultQuestionValues,
    questionFormValidation,
    questionToFormValues,
} from "../../forms/QuestionForm/questionFormValues.ts";

interface EditQuestionModalProps extends GenericModalProps {
    questionId: IdParam;
}

export const EditQuestionModal = ({onClose, questionId}: EditQuestionModalProps) => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const questionsQuery = useGetEventQuestions(eventId);
    const questionQuery = useGetQuestion(eventId, questionId);
    const mutation = useEditQuestion();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<QuestionFormValues>({
        initialValues: getDefaultQuestionValues(QuestionBelongsToType.ORDER),
        validate: questionFormValidation,
    });

    const [isHydrated, setIsHydrated] = useState(false);

    useEffect(() => {
        if (!questionQuery.data || isHydrated) {
            return;
        }

        const values = questionToFormValues(questionQuery.data);
        form.setValues(values);
        form.resetDirty(values);
        setIsHydrated(true);
    }, [questionQuery.data]);

    const handleSubmit = (values: QuestionFormValues) => {
        mutation.mutate({eventId, questionId, questionData: formValuesToRequest(values)}, {
            onSuccess: () => {
                showSuccess(t`Question updated`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    };

    return (
        <QuestionDrawer
            title={t`Edit Question`}
            form={form}
            loading={!isHydrated}
            submitLabel={t`Save Changes`}
            submitLoading={mutation.isPending}
            onSubmit={handleSubmit}
            onClose={onClose}
        >
            <QuestionForm
                form={form}
                productCategories={eventQuery.data?.product_categories}
                questions={questionsQuery.data ?? []}
                questionId={questionId}
            />
        </QuestionDrawer>
    );
};
