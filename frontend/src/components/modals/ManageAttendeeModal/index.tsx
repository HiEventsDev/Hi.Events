import {useParams} from "react-router";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {useUpdateAttendee} from "../../../mutations/useUpdateAttendee.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {Accordion} from "../../common/Accordion";
import {Button} from "../../common/Button";
import {Avatar, Box, Group, Stack, Tabs, Text, Textarea, TextInput} from "@mantine/core";
import {IconEdit, IconNotebook, IconQuestionMark, IconReceipt, IconTicket, IconUser} from "@tabler/icons-react";
import {LoadingMask} from "../../common/LoadingMask";
import {AttendeeDetails} from "../../common/AttendeeDetails";
import {OrderDetails} from "../../common/OrderDetails";
import {QuestionAndAnswerList, QuestionList} from "../../common/QuestionAndAnswerList";
import {AttendeeTicket} from "../../common/AttendeeTicket";
import {getInitials} from "../../../utilites/helpers.ts";
import {t} from "@lingui/macro";
import classes from './ManageAttendeeModal.module.scss';
import {useEffect, useState} from "react";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {ProductSelector} from "../../common/ProductSelector";
import {GenericModalProps, IdParam, ProductCategory, ProductType, QuestionAnswer} from "../../../types.ts";
import {InputGroup} from "../../common/InputGroup";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {EditAttendeeRequest} from "../../../api/attendee.client.ts";
import {AttendeeStatusBadge} from "../../common/AttendeeStatusBadge";
import {SideDrawer} from "../../common/SideDrawer";

interface ManageAttendeeModalProps extends GenericModalProps {
    onClose: () => void;
    attendeeId: IdParam;
}

export const ManageAttendeeModal = ({onClose, attendeeId}: ManageAttendeeModalProps) => {
    const {eventId} = useParams();
    const {data: attendee, refetch: refetchAttendee} = useGetAttendee(eventId, attendeeId);
    const {data: order} = useGetOrder(eventId, attendee?.order_id);
    const {data: event} = useGetEvent(eventId);
    const errorHandler = useFormErrorResponseHandler();
    const mutation = useUpdateAttendee();

    const form = useForm({
        initialValues: {
            first_name: "",
            last_name: "",
            email: "",
            notes: "",
            product_id: "",
            product_price_id: "",
        },
    });

    const [activeTab, setActiveTab] = useState("view");

    useEffect(() => {
        if (attendee) {
            form.initialize({
                first_name: attendee.first_name,
                last_name: attendee.last_name,
                email: attendee.email,
                notes: attendee.notes || "",
                product_id: String(attendee.product_id),
                product_price_id: String(attendee.product_price_id),
            });
        }
    }, [attendee]);

    useEffect(() => {
        if (!form.values.product_id) {
            return;
        }
        let productPriceId = event?.product_categories
            ?.flatMap(category => category.products)
            .find(product => product.id == form.values.product_id)?.prices?.[0]?.id;

        form.setValues({
            ...form.values,
            product_price_id: String(productPriceId),
        });
    }, [form.values.product_id]);

    if (!attendee || !order || !event) {
        return <LoadingMask/>;
    }

    const handleSubmit = (values: EditAttendeeRequest) => {
        mutation.mutate(
            {
                attendeeId,
                eventId,
                attendeeData: values,
            },
            {
                onSuccess: () => {
                    showSuccess(t`Successfully updated attendee`);
                    setActiveTab("view");
                },
                onError: (error) => errorHandler(form, error),
            }
        );
    };

    const fullName = `${attendee.first_name} ${attendee.last_name}`;
    const hasQuestions = attendee.question_answers && attendee.question_answers.length > 0;

    const detailsTab = (
        <div>
            <InputGroup>
                <TextInput {...form.getInputProps("first_name")} label={t`First name`} placeholder={t`Homer`} required/>
                <TextInput {...form.getInputProps("last_name")} label={t`Last name`} placeholder={t`Simpson`} required/>
            </InputGroup>
            <InputGroup>
                <TextInput {...form.getInputProps("email")} label={t`Email address`} placeholder="homer@simpson.com"
                           required/>
                {event?.product_categories && event.product_categories.length > 0 && (
                    <ProductSelector
                        placeholder={t`Select Product`}
                        label={t`Product`}
                        productCategories={event.product_categories as ProductCategory[]}
                        form={form}
                        productFieldName={"product_id"}
                        includedProductTypes={[ProductType.Ticket]}
                        multiSelect={false}
                        showTierSelector={true}
                    />
                )}
            </InputGroup>
            <Textarea
                label={<InputLabelWithHelp label={t`Notes`}
                                           helpText={t`Add any notes about the attendee. These will not be visible to the attendee.`}/>}
                {...form.getInputProps("notes")}
                placeholder={t`Add any notes about the attendee...`}
                minRows={3}
                maxRows={6}
                autosize
            />
        </div>
    );

    const viewContent = (
        <Accordion
            items={[
                {
                    value: "details",
                    icon: IconUser,
                    title: t`Attendee Details`,
                    content: <AttendeeDetails attendee={attendee}/>,
                },
                {
                    value: "notes",
                    icon: IconNotebook,
                    title: t`Attendee Notes`,
                    hidden: !attendee.notes,
                    content: (
                        <Box p="md">
                            <Text style={{whiteSpace: 'pre-line'}}>
                                {attendee.notes}
                            </Text>
                        </Box>
                    ),
                },
                {
                    value: "order",
                    icon: IconReceipt,
                    title: t`Order Details`,
                    content: <OrderDetails order={order} event={event} cardVariant="noStyle"/>,
                },
                {
                    value: "ticket",
                    icon: IconTicket,
                    title: t`Attendee Ticket`,
                    content: attendee.product ? (
                        <AttendeeTicket event={event} attendee={attendee} product={attendee.product}/>
                    ) : (
                        <Text c="dimmed" ta="center" py="xl">
                            {t`No product associated with this attendee.`}
                        </Text>
                    ),
                },
                {
                    value: "questions",
                    icon: IconQuestionMark,
                    title: t`Questions & Answers`,
                    count: hasQuestions ? attendee?.question_answers?.length : undefined,
                    content: hasQuestions ? (
                        <QuestionList
                            onEditAnswer={refetchAttendee}
                            questions={attendee.question_answers as QuestionAnswer[]}
                        />
                    ) : (
                        <Text c="dimmed" ta="center" py="xl">
                            {t`No questions answered by this attendee.`}
                        </Text>
                    ),
                },
            ].filter(item => !item.hidden)}
            defaultValue="details"
        />
    );

    return (
        <SideDrawer opened onClose={onClose} size="lg" padding="md">
            <Stack className={classes.container}>
                <div className={classes.header}>
                    <Group justify="center" align="center">
                        <Group gap="sm" align="center">
                            <Avatar size="md" radius="xl">
                                {getInitials(fullName)}
                            </Avatar>
                            <div className={classes.attendeeInfo}>
                                <Text fz="md" fw={500}>{fullName}</Text>
                                <AttendeeStatusBadge attendee={attendee}/>
                            </div>
                        </Group>
                    </Group>
                </div>
                <Tabs value={activeTab} onChange={setActiveTab as any}>
                    <Tabs.List>
                        <Tabs.Tab value="view" leftSection={<IconUser size={16}/>}>{t`View`}</Tabs.Tab>
                        <Tabs.Tab value="edit" leftSection={<IconEdit size={16}/>}>{t`Edit`}</Tabs.Tab>
                    </Tabs.List>

                    <Box mt="md">
                        <Tabs.Panel value="view">{viewContent}</Tabs.Panel>
                        <Tabs.Panel value="edit">
                            <form onSubmit={form.onSubmit(handleSubmit)}>
                                <Stack gap="md">
                                    {detailsTab}
                                    <Button type="submit" fullWidth disabled={mutation.isPending}>
                                        {mutation.isPending ? t`Working...` : t`Save Changes`}
                                    </Button>
                                </Stack>
                            </form>
                        </Tabs.Panel>
                    </Box>
                </Tabs>
            </Stack>
        </SideDrawer>
    );
};
