import {createBrowserRouter, Navigate} from "react-router-dom";
import ErrorPage from "./error-page.tsx";

export const router = createBrowserRouter([
    {
        path: "",
        element: <Navigate to={'/manage/events'} replace/>
    },
    {
        path: "auth",
        async lazy() {
            let AuthLayout = await import("./components/layouts/AuthLayout");
            return {Component: AuthLayout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "login",
                async lazy() {
                    let Login = await import("./components/routes/auth/Login");
                    return {Component: Login.default};
                }
            },
            {
                path: "register",
                async lazy() {
                    let Register = await import("./components/routes/auth/Register");
                    return {Component: Register.default};
                }
            },
            {
                path: "forgot-password",
                async lazy() {
                    let ForgotPassword = await import("./components/routes/auth/ForgotPassword");
                    return {Component: ForgotPassword.default};
                }
            },
            {
                path: "reset-password/:token",
                async lazy() {
                    let ResetPassword = await import("./components/routes/auth/ResetPassword");
                    return {Component: ResetPassword.default};
                }
            },
            {
                path: "accept-invitation/:token",
                async lazy() {
                    let AcceptInvitation = await import("./components/routes/auth/AcceptInvitation");
                    return {Component: AcceptInvitation.default};
                }
            }
        ]
    },
    {
        path: "manage",
        errorElement: <ErrorPage/>,
        async lazy() {
            let DefaultLayout = await import("./components/layouts/DefaultLayout");
            return {Component: DefaultLayout.default};
        },
        children: [
            {
                path: "events",
                async lazy() {
                    let Dashboard = await import("./components/routes/events/Dashboard");
                    return {Component: Dashboard.default};
                }
            },
            {
                path: "organizer/:organizerId",
                async lazy() {
                    let OrganizerDashboard = await import("./components/routes/organizer/OrganizerDashboard");
                    return {Component: OrganizerDashboard.default};
                }
            },
            {
                path: "account",
                async lazy() {
                    let ManageAccount = await import("./components/routes/account/ManageAccount");
                    return {Component: ManageAccount.default};
                }
            },
            {
                path: "profile",
                async lazy() {
                    let ManageProfile = await import("./components/routes/profile/ManageProfile");
                    return {Component: ManageProfile.default};
                }
            },
            {
                path: "profile/confirm-email-change",
                async lazy() {
                    let ConfirmEmailChange = await import("./components/routes/profile/ConfirmEmailChange");
                    return {Component: ConfirmEmailChange.default};
                }
            },
            {
                path: "profile/confirm-email-address/:token",
                async lazy() {
                    let ConfirmEmailAddress = await import("./components/routes/profile/ConfirmEmailAddress");
                    return {Component: ConfirmEmailAddress.default};
                }
            },
        ]
    },
    {
        path: "welcome",
        async lazy() {
            let Welcome = await import("./components/routes/welcome");
            return {Component: Welcome.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                async lazy() {
                    let Welcome = await import("./components/routes/welcome");
                    return {Component: Welcome.default};
                }
            },
        ]
    },
    {
        path: "account",
        errorElement: <ErrorPage/>,
        async lazy() {
            let ManageAccount = await import("./components/routes/account/ManageAccount");
            return {Component: ManageAccount.default};
        },
        children: [
            {
                path: "",
                async lazy() {
                    let AccountSettings = await import("./components/routes/account/ManageAccount/sections/AccountSettings");
                    return {Component: AccountSettings.default};
                },
                children: [
                    {
                        path: "settings",
                        async lazy() {
                            let AccountSettings = await import("./components/routes/account/ManageAccount/sections/AccountSettings");
                            return {Component: AccountSettings.default};
                        }
                    },
                    {
                        path: "taxes-and-fees",
                        async lazy() {
                            let TaxSettings = await import("./components/routes/account/ManageAccount/sections/TaxSettings");
                            return {Component: TaxSettings.default};
                        }
                    },
                    {
                        path: "event-defaults",
                        async lazy() {
                            let EventDefaultsSettings = await import("./components/routes/account/ManageAccount/sections/EventDefaultsSettings");
                            return {Component: EventDefaultsSettings.default};
                        }
                    },
                    {
                        path: "users",
                        async lazy() {
                            let Users = await import("./components/routes/account/ManageAccount/sections/Users");
                            return {Component: Users.default};
                        }
                    },
                    {
                        path: "payment",
                        async lazy() {
                            let PaymentSettings = await import("./components/routes/account/ManageAccount/sections/PaymentSettings");
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
            let EventLayout = await import("./components/layouts/Event");
            return {Component: EventLayout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                async lazy() {
                    let EventDashboard = await import("./components/routes/event/EventDashboard");
                    return {Component: EventDashboard.default};
                }
            },
            {
                path: "dashboard",
                async lazy() {
                    let EventDashboard = await import("./components/routes/event/EventDashboard");
                    return {Component: EventDashboard.default};
                }
            },
            {
                path: "tickets",
                async lazy() {
                    let Tickets = await import("./components/routes/event/tickets");
                    return {Component: Tickets.default};
                }
            },
            {
                path: "attendees",
                async lazy() {
                    let Attendees = await import("./components/routes/event/attendees");
                    return {Component: Attendees.default};
                }
            },
            {
                path: "questions",
                async lazy() {
                    let Questions = await import("./components/routes/event/questions");
                    return {Component: Questions.default};
                }
            },
            {
                path: "orders",
                async lazy() {
                    let Orders = await import("./components/routes/event/orders");
                    return {Component: Orders.default};
                }
            },
            {
                path: "promo-codes",
                async lazy() {
                    let PromoCodes = await import("./components/routes/event/promo-codes");
                    return {Component: PromoCodes.default};
                }
            },
            {
                path: "affiliates",
                async lazy() {
                    let Affiliates = await import("./components/routes/event/affiliates");
                    return {Component: Affiliates.default};
                }
            },
            {
                path: "check-in",
                async lazy() {
                    let CheckIn = await import("./components/routes/event/check-in");
                    return {Component: CheckIn.default};
                }
            },
            {
                path: "messages",
                async lazy() {
                    let Messages = await import("./components/routes/event/messages");
                    return {Component: Messages.default};
                }
            },
            {
                path: "settings",
                async lazy() {
                    let Settings = await import("./components/routes/event/Settings");
                    return {Component: Settings.default};
                }
            },
            {
                path: "widget",
                async lazy() {
                    let Widget = await import("./components/routes/event/widget");
                    return {Component: Widget.default};
                }
            },
            {
                path: "homepage-designer",
                async lazy() {
                    let HomepageDesigner = await import("./components/routes/event/HomepageDesigner");
                    return {Component: HomepageDesigner.default};
                }
            },
            {
                path: "getting-started",
                async lazy() {
                    let GettingStarted = await import("./components/routes/event/GettingStarted");
                    return {Component: GettingStarted.default};
                }
            }
        ]
    },
    {
        path: "/event/:eventId/:eventSlug",
        async lazy() {
            let EventHomepage = await import("./components/layouts/EventHomepage");
            return {Component: EventHomepage.default};
        },
        errorElement: <ErrorPage/>,
    },
    {
        path: "/widget/:eventId",
        async lazy() {
            let TicketWidget = await import("./components/layouts/TicketWidget");
            return {Component: TicketWidget.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                async lazy() {
                    let SelectTickets = await import("./components/routes/ticket-widget/SelectTickets");
                    return {Component: SelectTickets.default};
                }
            },
        ]
    },
    {
        path: "/checkout/:eventId",
        async lazy() {
            let Checkout = await import("./components/layouts/Checkout");
            return {Component: Checkout.default};
        },
        errorElement: <ErrorPage/>,
        children: [
            {
                path: ":orderShortId/details",
                async lazy() {
                    let CollectInformation = await import("./components/routes/ticket-widget/CollectInformation");
                    return {Component: CollectInformation.default};
                }
            },
            {
                path: ":orderShortId/payment",
                async lazy() {
                    let Payment = await import("./components/routes/ticket-widget/Payment");
                    return {Component: Payment.default};
                }
            },
            {
                path: ":orderShortId/summary",
                async lazy() {
                    let OrderSummaryAndTickets = await import("./components/routes/ticket-widget/OrderSummaryAndTickets");
                    return {Component: OrderSummaryAndTickets.default};
                }
            },
            {
                path: ":orderShortId/payment_return",
                async lazy() {
                    let PaymentReturn = await import("./components/routes/ticket-widget/PaymentReturn");
                    return {Component: PaymentReturn.default};
                }
            },
        ]
    },
    {
        path: "/order/:eventId/:orderShortId/print",
        async lazy() {
            let PrintOrder = await import("./components/routes/ticket-widget/PrintOrder");
            return {Component: PrintOrder.default};
        },
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId/print",
        async lazy() {
            let PrintTicket = await import("./components/routes/ticket-widget/PrintTicket");
            return {Component: PrintTicket.default};
        },
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId",
        async lazy() {
            let AttendeeTicketAndInformation = await import("./components/routes/ticket-widget/AttendeeTicketAndInformation");
            return {Component: AttendeeTicketAndInformation.default};
        },
        errorElement: <ErrorPage/>
    },
], {
    future: {
        v7_normalizeFormMethod: true,
    }
});

