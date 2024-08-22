import {Navigate, RouteObject} from "react-router-dom";
import ErrorPage from "./error-page.tsx";
import {eventsClientPublic} from "./api/event.client.ts";
import {promoCodeClientPublic} from "./api/promo-code.client.ts";
import {useEffect, useState} from "react";
import {useGetMe} from "./queries/useGetMe.ts";

const Root = () => {
    const [redirectPath, setRedirectPath] = useState<string | null>(null);
    const me = useGetMe();

    useEffect(() => {
        if (me.isFetched) {
            setRedirectPath(me.isSuccess ? "/manage/events" : "/auth/login");
        }
    }, [me.isFetched]);

    if (redirectPath) {
        return <Navigate to={redirectPath} replace={true}/>;
    }
};

export const router: RouteObject[] = [
    {
        path: "",
        element: <Root/>,
        errorElement: <ErrorPage/>
    },
    {
        path: "auth",
        async lazy() {
            const AuthLayout = await import("./components/layouts/AuthLayout");
            return {Component: AuthLayout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "login",
                async lazy() {
                    const Login = await import("./components/routes/auth/Login");
                    return {Component: Login.default};
                },
            },
            {
                path: "register",
                async lazy() {
                    const Register = await import("./components/routes/auth/Register");
                    return {Component: Register.default};
                }
            },
            {
                path: "forgot-password",
                async lazy() {
                    const ForgotPassword = await import("./components/routes/auth/ForgotPassword");
                    return {Component: ForgotPassword.default};
                }
            },
            {
                path: "reset-password/:token",
                async lazy() {
                    const ResetPassword = await import("./components/routes/auth/ResetPassword");
                    return {Component: ResetPassword.default};
                }
            },
            {
                path: "accept-invitation/:token",
                async lazy() {
                    const AcceptInvitation = await import("./components/routes/auth/AcceptInvitation");
                    return {Component: AcceptInvitation.default};
                }
            }
        ]
    },
    {
        path: "manage",
        errorElement: <ErrorPage/>,
        async lazy() {
            const DefaultLayout = await import("./components/layouts/DefaultLayout");
            return {Component: DefaultLayout.default};
        },
        children: [
            {
                path: "events/:eventsState?",
                async lazy() {
                    const Dashboard = await import("./components/routes/events/Dashboard");
                    return {Component: Dashboard.default};
                },
            },
            {
                path: "organizer/:organizerId/events?/:eventsState?",
                async lazy() {
                    const OrganizerDashboard = await import("./components/routes/organizer/OrganizerDashboard");
                    return {Component: OrganizerDashboard.default};
                }
            },
            {
                path: "account",
                async lazy() {
                    const ManageAccount = await import("./components/routes/account/ManageAccount");
                    return {Component: ManageAccount.default};
                }
            },
            {
                path: "profile",
                async lazy() {
                    const ManageProfile = await import("./components/routes/profile/ManageProfile");
                    return {Component: ManageProfile.default};
                }
            },
            {
                path: "profile/confirm-email-change/:token",
                async lazy() {
                    const ConfirmEmailChange = await import("./components/routes/profile/ConfirmEmailChange");
                    return {Component: ConfirmEmailChange.default};
                }
            },
            {
                path: "profile/confirm-email-address/:token",
                async lazy() {
                    const ConfirmEmailAddress = await import("./components/routes/profile/ConfirmEmailAddress");
                    return {Component: ConfirmEmailAddress.default};
                }
            },
        ]
    },
    {
        path: "welcome",
        async lazy() {
            const DefaultLayout = await import("./components/layouts/DefaultLayout");
            return {Component: DefaultLayout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                async lazy() {
                    const Welcome = await import("./components/routes/welcome");
                    return {Component: Welcome.default};
                }
            },
        ]
    },
    {
        path: "account",
        errorElement: <ErrorPage/>,
        async lazy() {
            const DefaultLayout = await import("./components/layouts/DefaultLayout");
            return {Component: DefaultLayout.default};
        },
        children: [
            {
                path: "",
                async lazy() {
                    const ManageAccount = await import("./components/routes/account/ManageAccount");
                    return {Component: ManageAccount.default};
                },
                children: [
                    {
                        path: "settings",
                        async lazy() {
                            const AccountSettings = await import("./components/routes/account/ManageAccount/sections/AccountSettings");
                            return {Component: AccountSettings.default};
                        }
                    },
                    {
                        path: "taxes-and-fees",
                        async lazy() {
                            const TaxSettings = await import("./components/routes/account/ManageAccount/sections/TaxSettings");
                            return {Component: TaxSettings.default};
                        }
                    },
                    {
                        path: "event-defaults",
                        async lazy() {
                            const EventDefaultsSettings = await import("./components/routes/account/ManageAccount/sections/EventDefaultsSettings");
                            return {Component: EventDefaultsSettings.default};
                        }
                    },
                    {
                        path: "users",
                        async lazy() {
                            const Users = await import("./components/routes/account/ManageAccount/sections/Users");
                            return {Component: Users.default};
                        }
                    },
                    {
                        path: "payment",
                        async lazy() {
                            const PaymentSettings = await import("./components/routes/account/ManageAccount/sections/PaymentSettings");
                            return {Component: PaymentSettings.default};
                        }
                    },
                ]
            },
        ]
    },
    {
        path: "/manage/event/:eventId",
        async lazy() {
            const EventLayout = await import("./components/layouts/Event");
            return {Component: EventLayout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                async lazy() {
                    const EventDashboard = await import("./components/routes/event/EventDashboard");
                    return {Component: EventDashboard.default};
                }
            },
            {
                path: "dashboard",
                async lazy() {
                    const EventDashboard = await import("./components/routes/event/EventDashboard");
                    return {Component: EventDashboard.default};
                }
            },
            {
                path: "tickets",
                async lazy() {
                    const Tickets = await import("./components/routes/event/tickets");
                    return {Component: Tickets.default};
                }
            },
            {
                path: "attendees",
                async lazy() {
                    const Attendees = await import("./components/routes/event/attendees");
                    return {Component: Attendees.default};
                }
            },
            {
                path: "questions",
                async lazy() {
                    const Questions = await import("./components/routes/event/questions");
                    return {Component: Questions.default};
                }
            },
            {
                path: "orders",
                async lazy() {
                    const Orders = await import("./components/routes/event/orders");
                    return {Component: Orders.default};
                }
            },
            {
                path: "promo-codes",
                async lazy() {
                    const PromoCodes = await import("./components/routes/event/promo-codes");
                    return {Component: PromoCodes.default};
                }
            },
            {
                path: "affiliates",
                async lazy() {
                    const Affiliates = await import("./components/routes/event/affiliates");
                    return {Component: Affiliates.default};
                }
            },
            {
                path: "check-in",
                async lazy() {
                    const CheckIn = await import("./components/routes/event/CheckInLists");
                    return {Component: CheckIn.default};
                }
            },
            {
                path: "messages",
                async lazy() {
                    const Messages = await import("./components/routes/event/messages");
                    return {Component: Messages.default};
                }
            },
            {
                path: "settings",
                async lazy() {
                    const Settings = await import("./components/routes/event/Settings");
                    return {Component: Settings.default};
                }
            },
            {
                path: "widget",
                async lazy() {
                    const Widget = await import("./components/routes/event/widget");
                    return {Component: Widget.default};
                }
            },
            {
                path: "homepage-designer",
                async lazy() {
                    const HomepageDesigner = await import("./components/routes/event/HomepageDesigner");
                    return {Component: HomepageDesigner.default};
                }
            },
            {
                path: "getting-started",
                async lazy() {
                    const GettingStarted = await import("./components/routes/event/GettingStarted");
                    return {Component: GettingStarted.default};
                }
            },
            {
                path: "capacity-assignments",
                async lazy() {
                    const CapacityAssignments = await import("./components/routes/event/CapacityAssignments");
                    return {Component: CapacityAssignments.default};
                }
            }
        ]
    },
    {
        path: "/e/:eventId/:eventSlug",
        async lazy() {
            const EventHomepage = await import("./components/layouts/EventHomepage");
            return {Component: EventHomepage.default};
        },
        errorElement: <ErrorPage/>,
    },
    {
        path: "/event/:eventId/:eventSlug",
        loader: async ({params, request}) => {
            try {
                const url = new URL(request.url)
                const queryParams = new URLSearchParams(url.search);
                const promoCode = queryParams.get("promo_code") ?? null
                const {data: event} = await eventsClientPublic.findByID(params.eventId, promoCode);
                let promoCodeValid: undefined | boolean = undefined;
                if (promoCode) {
                    promoCodeValid = (await promoCodeClientPublic.validateCode(params.eventId, promoCode)).valid;
                }

                return {event, promoCodeValid, promoCode};
            } catch (error: any) {
                // for 404s we want to return null so that the 404 page is shown
                if (error?.response?.status === 404) {
                    return {event: null, promoCodeValid: undefined, promoCode: null};
                }

                console.error(error);

                throw error;
            }
        },
        async lazy() {
            const PublicEvent = await import("./components/layouts/PublicEvent");
            return {Component: PublicEvent.default};
        },
        errorElement: <ErrorPage/>,
    },
    {
        path: "/widget/:eventId",
        async lazy() {
            const TicketWidget = await import("./components/layouts/TicketWidget");
            return {Component: TicketWidget.default};
        },
        errorElement: <ErrorPage/>,
    },
    {
        path: "/checkout/:eventId",
        async lazy() {
            const Checkout = await import("./components/layouts/Checkout");
            return {Component: Checkout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: ":orderShortId/details",
                async lazy() {
                    const CollectInformation = await import("./components/routes/ticket-widget/CollectInformation");
                    return {Component: CollectInformation.default};
                }
            },
            {
                path: ":orderShortId/payment",
                async lazy() {
                    const Payment = await import("./components/routes/ticket-widget/Payment");
                    return {Component: Payment.default};
                }
            },
            {
                path: ":orderShortId/summary",
                async lazy() {
                    const OrderSummaryAndTickets = await import("./components/routes/ticket-widget/OrderSummaryAndTickets");
                    return {Component: OrderSummaryAndTickets.default};
                }
            },
            {
                path: ":orderShortId/payment_return",
                async lazy() {
                    const PaymentReturn = await import("./components/routes/ticket-widget/PaymentReturn");
                    return {Component: PaymentReturn.default};
                }
            },
        ]
    },
    {
        path: "/order/:eventId/:orderShortId/print",
        async lazy() {
            const PrintOrder = await import("./components/routes/ticket-widget/PrintOrder");
            return {Component: PrintOrder.default};
        },
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId/print",
        async lazy() {
            const PrintTicket = await import("./components/routes/ticket-widget/PrintTicket");
            return {Component: PrintTicket.default};
        },
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId",
        async lazy() {
            const AttendeeTicketAndInformation = await import("./components/routes/ticket-widget/AttendeeTicketAndInformation");
            return {Component: AttendeeTicketAndInformation.default};
        },
        errorElement: <ErrorPage/>
    },
    {
        path: "/check-in/:checkInListShortId",
        async lazy() {
            const CheckIn = await import("./components/layouts/CheckIn");
            return {Component: CheckIn.default};
        },
        errorElement: <ErrorPage/>,
    }
];

