import {useParams} from "react-router";
import {useGetAttendee} from "../../../queries/useGetAttendee.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetOrder} from "../../../queries/useGetOrder.ts";
import {useUpdateAttendee} from "../../../mutations/useUpdateAttendee.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {Anchor, Avatar, Button, Text, Textarea, TextInput} from "@mantine/core";
import {EntityActionBar} from "../../common/EntityActions";
import {useAttendeeActions} from "../../../hooks/useAttendeeActions.tsx";
import {QuestionList} from "../../common/QuestionAndAnswerList";
import {ManageOrderModal} from "../ManageOrderModal";
import {AttendeeTicket} from "../../common/AttendeeTicket";
import {getInitials} from "../../../utilites/helpers.ts";
import {t} from "@lingui/macro";
import classes from './ManageAttendeeModal.module.scss';
import {useEffect, useMemo, useState} from "react";
import {AttendeeSeatField} from "../../../ee/seating/components/AttendeeSeatField";
import {indexLayout, bandOf} from "../../../ee/seating/components/lib/layoutIndex.ts";
import {useGetEventSeatMap} from "../../../ee/seating/queries/useGetEventSeatMap.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {ProductSelector} from "../../common/ProductSelector";
import {GenericModalProps, IdParam, Product, ProductCategory, ProductType, QuestionAnswer} from "../../../types.ts";
import {InputGroup} from "../../common/InputGroup";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";
import {EditAttendeeRequest} from "../../../api/attendee.client.ts";
import {AttendeeStatusBadge} from "../../common/AttendeeStatusBadge";
import {getAttendeeProductTitle} from "../../../utilites/products.ts";
import {getLocaleName, SupportedLocales} from "../../../locales.ts";
import {relativeDate} from "../../../utilites/dates.ts";
import {
    DrawerStat,
    SideDrawer,
    SideDrawerFields,
    SideDrawerHeading,
    SideDrawerSection,
    SideDrawerStats
} from "../../common/SideDrawer";

interface ManageAttendeeModalProps extends GenericModalProps {
    onClose: () => void;
    attendeeId: IdParam;
}

const FORM_ID = 'manage-attendee-form';

export const ManageAttendeeModal = ({onClose, attendeeId}: ManageAttendeeModalProps) => {
    const {eventId} = useParams();
    const {data: attendee, refetch: refetchAttendee} = useGetAttendee(eventId, attendeeId);
    const {data: order} = useGetOrder(eventId, attendee?.order_id);
    const {data: event} = useGetEvent(eventId);
    const seatMap = useGetEventSeatMap(eventId, !!event?.has_seat_map && !!attendee?.seat_uid).data;
    const seatBandProductIds = useMemo(() => {
        if (!seatMap || !attendee?.seat_uid) {
            return null;
        }
        const band = bandOf(indexLayout(seatMap.layout), attendee.seat_uid);
        return new Set(seatMap.band_products
            .filter(link => link.band_key === band)
            .flatMap(band => band.products.map(link => link.product_id)));
    }, [seatMap, attendee?.seat_uid]);
    const errorHandler = useFormErrorResponseHandler();
    const mutation = useUpdateAttendee();
    const {getAttendeeActions, attendeeActionModals} = useAttendeeActions({
        eventId,
        onEdit: () => setIsEditing(true),
    });

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

    const [isEditing, setIsEditing] = useState(false);
    const [showOrder, setShowOrder] = useState(false);

    useEffect(() => {
        if (attendee) {
            form.initialize({
                first_name: attendee.first_name,
                last_name: attendee.last_name,
                email: attendee.email ?? "",
                notes: attendee.notes || "",
                product_id: String(attendee.product_id),
                product_price_id: attendee.product_price_id ? String(attendee.product_price_id) : "",
            });
        }
    }, [attendee]);

    useEffect(() => {
        if (!form.values.product_id) {
            return;
        }
        let productPriceId = event?.product_categories
            ?.flatMap(category => category.products)
            .find(product => String(product?.id) === String(form.values.product_id))?.prices?.[0]?.id;

        form.setValues({
            ...form.values,
            product_price_id: productPriceId ? String(productPriceId) : "",
        });
    }, [form.values.product_id]);

    if (!attendee || !order || !event) {
        return <SideDrawer opened={true} onClose={onClose} loading/>;
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
                    setIsEditing(false);
                },
                onError: (error) => errorHandler(form, error),
            }
        );
    };

    const handleCancelEdit = () => {
        form.reset();
        setIsEditing(false);
    };

    const productCategories = (event.product_categories ?? []).map(category => seatBandProductIds ? {
        ...category,
        products: category.products?.filter(product => seatBandProductIds.has(Number(product.id))),
    } : category) as ProductCategory[];

    const fullName = `${attendee.first_name} ${attendee.last_name}`;
    const questionAnswers = attendee.question_answers ?? [];
    const checkIns = attendee.check_ins ?? [];

    const stats: DrawerStat[] = [
        {
            label: t`Ticket`,
            value: getAttendeeProductTitle(attendee, attendee.product as Product),
        },
        {
            label: t`Status`,
            value: <AttendeeStatusBadge attendee={attendee}/>,
        },
        {
            label: t`Check-In`,
            value: checkIns.length > 0 ? relativeDate(checkIns[0].created_at) : t`Not checked in`,
        },
        {
            label: t`Order`,
            value: (
                <Anchor className={classes.reference} onClick={() => setShowOrder(true)}>
                    {order.public_id}
                </Anchor>
            ),
        },
    ];

    const fields: DrawerStat[] = [
        ...(attendee.seat_uid ? [{
            label: t`Seat`,
            value: <AttendeeSeatField eventId={eventId} attendee={attendee}/>,
        }] : []),
        {
            label: t`Email`,
            value: attendee.email
                ? <Anchor href={'mailto:' + attendee.email} target="_blank">{attendee.email}</Anchor>
                : t`No email provided`,
        },
        {
            label: t`Language`,
            value: getLocaleName(attendee.locale as SupportedLocales),
        },
        ...(checkIns.length > 0 ? [{
            label: t`Check-Ins`,
            value: checkIns.map(checkIn => (
                <div key={checkIn.id}>
                    {checkIn.check_in_list?.name} · {relativeDate(checkIn.created_at)}
                </div>
            )),
        }] : []),
    ];

    const header = (
        <SideDrawerHeading
            media={(
                <Avatar size={42} radius="xl" variant="light" color="primary">
                    {getInitials(fullName)}
                </Avatar>
            )}
            title={<span>{fullName}</span>}
            subtitle={attendee.email ? <span>{attendee.email}</span> : null}
        />
    );

    const footer = isEditing && (
        <>
            <Button variant="default" onClick={handleCancelEdit}>
                {t`Cancel`}
            </Button>
            <Button type="submit" form={FORM_ID} loading={mutation.isPending}>
                {t`Save Changes`}
            </Button>
        </>
    );

    return (
        <SideDrawer
            opened={true}
            onClose={onClose}
            header={header}
            footer={footer}
        >
            {isEditing ? (
                <form id={FORM_ID} onSubmit={form.onSubmit(handleSubmit)} className={classes.form}>
                    <InputGroup>
                        <TextInput {...form.getInputProps("first_name")} label={t`First name`}
                                   placeholder={t`Homer`} required/>
                        <TextInput {...form.getInputProps("last_name")} label={t`Last name`}
                                   placeholder={t`Simpson`} required={!order.box_office_id}/>
                    </InputGroup>
                    <InputGroup>
                        <TextInput {...form.getInputProps("email")} label={t`Email address`}
                                   placeholder="homer@simpson.com" required={!order.box_office_id}/>
                        {productCategories.length > 0 && (
                            <ProductSelector
                                placeholder={t`Select Product`}
                                label={t`Product`}
                                productCategories={productCategories}
                                form={form}
                                productFieldName={"product_id"}
                                includedProductTypes={[ProductType.Ticket]}
                                multiSelect={false}
                                showTierSelector={true}
                            />
                        )}
                    </InputGroup>
                    <Textarea
                        label={<InputLabelWithHelp
                            label={t`Notes`}
                            helpText={t`Add any notes about the attendee. These will not be visible to the attendee.`}/>}
                        {...form.getInputProps("notes")}
                        placeholder={t`Add any notes about the attendee...`}
                        minRows={3}
                        maxRows={6}
                        autosize
                    />
                </form>
            ) : (
                <>
                    <SideDrawerStats stats={stats}/>

                    <div className={classes.actions}>
                        <EntityActionBar actions={getAttendeeActions(attendee)}/>
                    </div>

                    <SideDrawerSection title={t`Details`}>
                        <SideDrawerFields fields={fields}/>
                    </SideDrawerSection>

                    {attendee.notes && (
                        <SideDrawerSection title={t`Notes`}>
                            <Text size="sm" className={classes.notes}>{attendee.notes}</Text>
                        </SideDrawerSection>
                    )}

                    {attendee.product && (
                        <SideDrawerSection title={t`Ticket`}>
                            <AttendeeTicket event={event} attendee={attendee} product={attendee.product}/>
                        </SideDrawerSection>
                    )}

                    {questionAnswers.length > 0 && (
                        <SideDrawerSection title={t`Questions & Answers`} count={questionAnswers.length}>
                            <QuestionList
                                hideProductTitle
                                onEditAnswer={refetchAttendee}
                                questions={questionAnswers as QuestionAnswer[]}
                            />
                        </SideDrawerSection>
                    )}
                </>
            )}

            {attendeeActionModals}

            {showOrder && (
                <ManageOrderModal orderId={attendee.order_id} onClose={() => setShowOrder(false)}/>
            )}
        </SideDrawer>
    );
};
