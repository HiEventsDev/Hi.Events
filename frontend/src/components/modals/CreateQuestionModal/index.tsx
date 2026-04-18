import {Button, Group} from "@mantine/core";
import {ContactAttributeDefinition, GenericModalProps, Question, QuestionRequestData, QuestionType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {notifications} from "@mantine/notifications";
import {useParams} from "react-router";
import {questionClient} from "../../../api/question.client.ts";
import {contactAttributeDefinitionClient} from "../../../api/contact-attribute-definition.client.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../../../queries/useGetEventQuestions.ts";
import {GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY} from "../../../queries/useGetContactAttributeDefinitions.ts";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {
    QuestionForm,
    definitionTypeFromQuestionType,
    slugifyToAttributeName,
} from "../../forms/QuestionForm";
import {showError} from "../../../utilites/notifications.tsx";

interface CreateQuestionModalProps extends GenericModalProps {
    onCompleted: (question: Question) => void;
    defaultBelongsTo?: 'ORDER' | 'PRODUCT';
}

interface QuestionFormValues {
    title: string;
    description: string;
    type: string;
    required: boolean;
    options: string[];
    product_ids: number[] | string[];
    apply_to_all_products: boolean;
    belongs_to: string;
    is_hidden: boolean;
    contact_attribute_definition_id: number | null;
    __reusable_selection: string;
    __make_reusable: boolean;
}

export const CreateQuestionModal = ({onClose, onCompleted, defaultBelongsTo = 'ORDER'}: CreateQuestionModalProps) => {
    const {eventId} = useParams();
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    const eventQuery = useGetEvent(eventId);
    const productCategories = eventQuery?.data?.product_categories;

    const form = useForm<QuestionFormValues>({
        initialValues: {
            title: "",
            description: "",
            type: QuestionType.SINGLE_LINE_TEXT.toString(),
            required: false,
            options: [],
            product_ids: [],
            apply_to_all_products: true,
            belongs_to: defaultBelongsTo,
            is_hidden: false,
            contact_attribute_definition_id: null,
            __reusable_selection: '__new__',
            __make_reusable: false,
        },
    });

    const mutation = useMutation({
        mutationFn: async (values: QuestionFormValues) => {
            let contact_attribute_definition_id = values.contact_attribute_definition_id;

            if (values.__reusable_selection === '__new__' && values.__make_reusable) {
                const definitionType = definitionTypeFromQuestionType(values.type);
                const payload: Partial<ContactAttributeDefinition> = {
                    name: slugifyToAttributeName(values.title),
                    label: values.title,
                    type: definitionType,
                    is_active: true,
                    sort_order: 0,
                };
                if (definitionType !== 'text' && values.options.length > 0) {
                    payload.options = values.options;
                }
                const response = await contactAttributeDefinitionClient.create(me?.account_id, payload);
                contact_attribute_definition_id = response.data.id ?? null;
                queryClient.invalidateQueries({queryKey: [GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY]});
            }

            const {
                __reusable_selection: _a,
                __make_reusable: _b,
                apply_to_all_products: _c,
                ...rest
            } = values;

            return questionClient.create(eventId, {
                ...rest,
                contact_attribute_definition_id,
            } as QuestionRequestData);
        },

        onSuccess: ({data: question}) => {
            notifications.show({
                message: t`Successfully Created Question`,
                color: 'green',
                position: 'top-center',
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
        }
    });

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Create Question`}
        >
            <form onSubmit={form.onSubmit((values) => mutation.mutate(values))}>
                <QuestionForm form={form} productCategories={productCategories}/>
                <Group justify="flex-end" mt="xl" mb="md">
                    <Button variant="default" onClick={onClose} disabled={mutation.isPending}>
                        {t`Cancel`}
                    </Button>
                    <Button loading={mutation.isPending} type="submit">
                        {mutation.isPending ? t`Working...` : t`Create Question`}
                    </Button>
                </Group>
            </form>
        </Modal>
    )
};
