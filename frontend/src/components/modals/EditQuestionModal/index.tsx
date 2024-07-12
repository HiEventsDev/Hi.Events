import {Button, LoadingOverlay} from "@mantine/core";
import {GenericModalProps, IdParam, Question, QuestionRequestData, QuestionType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {notifications} from "@mantine/notifications";
import {useParams} from "react-router-dom";
import {questionClient} from "../../../api/question.client.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../../../queries/useGetEventQuestions.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {QuestionForm} from "../../forms/QuestionForm";
import {GET_QUESTION_QUERY_KEY, useGetQuestion} from "../../../queries/useGetQuestion.ts";
import {useEffect} from "react";

interface EditQuestionModalProps extends GenericModalProps {
    questionId: IdParam;
}

export const EditQuestionModal = ({onClose, questionId}: EditQuestionModalProps) => {
    const {eventId} = useParams();
    const queryClient = useQueryClient();

    const eventQuery = useGetEvent(eventId);
    const questionQuery = useGetQuestion(eventId, questionId);
    const tickets = eventQuery?.data?.tickets;

    const form = useForm<QuestionRequestData>({
        initialValues: {
            title: "",
            description: "",
            type: QuestionType.SINGLE_LINE_TEXT.toString(),
            required: false,
            options: [],
            ticket_ids: [],
            belongs_to: "ORDER",
            is_hidden: false,
        },
    });

    useEffect(() => {
            const {data} = questionQuery;

            if (!data) {
                return;
            }

            form.setValues({
                title: data.title,
                description: data.description,
                type: data.type,
                required: data.required,
                options: data.options,
                ticket_ids: data.ticket_ids?.map(id => String(id)),
                belongs_to: data.belongs_to,
                is_hidden: data.is_hidden,
            });
        }
        , [questionQuery.isFetched]);

    const mutation = useMutation(
        (questionData: Question) => questionClient.update(eventId, questionId, questionData),
        {
            onSuccess: () => {
                notifications.show({
                    message: t`Successfully Created Question`,
                    color: 'green',
                });
                queryClient.invalidateQueries({queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY, eventId]}).then(() => {
                    form.reset();
                    onClose();
                }).then(() => {
                        queryClient.invalidateQueries({queryKey: [GET_QUESTION_QUERY_KEY, eventId, questionId]});
                    }
                )
            },
            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                }
                notifications.show({
                    message: t`Unable to update question. Please check the your details`,
                    color: 'red',
                });
            },
        }
    );

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Edit Question`}
        >
            <form onSubmit={form.onSubmit((values) => mutation.mutate(values as any as Question))}>
                <QuestionForm form={form} tickets={tickets}/>
                {!questionQuery.isFetched && <LoadingOverlay visible/>}
                <Button loading={mutation.isLoading} type="submit" fullWidth mt="xl">
                    {mutation.isLoading ? t`Working...` : t`Edit Question`}
                </Button>
            </form>
        </Modal>
    )
};
