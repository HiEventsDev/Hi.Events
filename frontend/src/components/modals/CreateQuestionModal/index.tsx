import {Button} from "@mantine/core";
import {GenericModalProps, Question, QuestionRequestData, QuestionType} from "../../../types.ts";
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
import {showError} from "../../../utilites/notifications.tsx";

interface CreateQuestionModalProps extends GenericModalProps {
    onCompleted: (question: Question) => void;
}

export const CreateQuestionModal = ({onClose, onCompleted}: CreateQuestionModalProps) => {
    const {eventId} = useParams();
    const queryClient = useQueryClient();

    const eventQuery = useGetEvent(eventId);
    const tickets = eventQuery?.data?.tickets;

    const form = useForm({
        initialValues: {
            title: "",
            description: "",
            type: QuestionType.SINGLE_LINE_TEXT.toString(),
            required: false,
            options: [],
            ticket_ids: [],
            apply_to_all_tickets: true,
            belongs_to: "ORDER",
            is_hidden: false,
        },
    });

    const mutation = useMutation(
        (questionData: Question) => questionClient.create(eventId, questionData as QuestionRequestData),
        {
            onSuccess: ({data: question}) => {
                notifications.show({
                    message: t`Successfully Created Question`,
                    color: 'green',
                });
                queryClient.invalidateQueries({queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY]}).then(() => {
                    onCompleted(question);
                    onClose();
                    form.reset();
                });
            },
            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                } else {
                    showError(t`Unable to create question. Please check the your details`);
                }
            },
        }
    );

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Create Question`}
        >
            <form onSubmit={form.onSubmit((values) => mutation.mutate(values as any as Question))}>
                <QuestionForm form={form} tickets={tickets}/>
                <Button loading={mutation.isLoading} type="submit" fullWidth mt="xl">
                    {mutation.isLoading ? t`Working...` : t`Create Question`}
                </Button>
            </form>
        </Modal>
    )
};
