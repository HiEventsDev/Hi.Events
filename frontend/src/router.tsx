import {createBrowserRouter, Navigate} from "react-router-dom";
import {Login} from "./components/routes/auth/Login";
import {Register} from "./components/routes/auth/Register";
import {ForgotPassword} from "./components/routes/auth/ForgotPassword";
import {Tickets} from "./components/routes/event/tickets.tsx";
import {Attendees} from "./components/routes/event/attendees.tsx";
import {Questions} from "./components/routes/event/questions.tsx";
import {Settings} from "./components/routes/event/Settings";
import {Dashboard} from "./components/routes/events/Dashboard";
import {EventDashboard} from "./components/routes/event/EventDashboard";
import {SelectTickets} from "./components/routes/ticket-widget/SelectTickets";
import {CollectInformation} from "./components/routes/ticket-widget/CollectInformation";
import {OrderSummaryAndTickets} from "./components/routes/ticket-widget/OrderSummaryAndTickets";
import ErrorPage from "./error-page.tsx";
import {Orders} from "./components/routes/event/orders.tsx";
import {AuthLayout} from "./components/layouts/AuthLayout";
import {EventLayout} from "./components/layouts/Event";
import {Payment} from "./components/routes/ticket-widget/Payment";
import {PromoCodes} from "./components/routes/event/promo-codes.tsx";
import {Affiliates} from "./components/routes/event/affiliates.tsx";
import {CheckIn} from "./components/routes/event/check-in.tsx";
import {AttendeeTicketAndInformation} from "./components/routes/ticket-widget/AttendeeTicketAndInformation";
import {PaymentReturn} from "./components/routes/ticket-widget/PaymentReturn";
import {Messages} from "./components/routes/event/messages.tsx";
import {ManageAccount} from "./components/routes/account/ManageAccount";
import {Widget} from "./components/routes/event/widget.tsx";
import {Index} from "./components/routes/auth/ResetPassword";
import {DefaultLayout} from "./components/layouts/DefaultLayout";
import {ManageProfile} from "./components/routes/profile/ManageProfile";
import {ConfirmEmailChange} from "./components/routes/profile/ConfirmEmailChange";
import {AccountSettings} from "./components/routes/account/ManageAccount/sections/AccountSettings";
import {TaxSettings} from "./components/routes/account/ManageAccount/sections/TaxSettings";
import {EventDefaultsSettings} from "./components/routes/account/ManageAccount/sections/EventDefaultsSettings";
import {PaymentSettings} from "./components/routes/account/ManageAccount/sections/PaymentSettings";
import {Users} from "./components/routes/account/ManageAccount/sections/Users";
import {AcceptInvitation} from "./components/routes/auth/AcceptInvitation";
import {EventHomepage} from "./components/layouts/EventHomepage";
import {Welcome} from "./components/routes/welcome";
import {GettingStarted} from "./components/routes/event/GettingStarted";
import {TicketWidget} from "./components/layouts/TicketWidget";
import {Checkout} from "./components/layouts/Checkout";
import {HomepageDesigner} from "./components/routes/event/HomepageDesigner";
import {OrganizerDashboard} from "./components/routes/organizer/OrganizerDashboard";
import {ConfirmEmailAddress} from "./components/routes/profile/ConfirmEmailAddress";
import {PrintTicket} from "./components/routes/ticket-widget/PrintTicket";
import {PrintOrder} from "./components/routes/ticket-widget/PrintOrder";

export const router = createBrowserRouter([
    {
        path: "",
        element: <Navigate to={'/manage/events'} replace/>
    },
    {
        path: "manage",
        errorElement: <ErrorPage/>,
        element: <DefaultLayout/>,
        children: [
            {
                path: "events",
                element: <Dashboard/>,
            },
            {
                path: "organizer/:organizerId",
                element: <OrganizerDashboard/>,
            },
            {
                path: "account",
                element: <ManageAccount/>,
            },
            {
                path: "profile",
                element: <ManageProfile/>,
            },
            {
                path: "profile/confirm-email-change",
                element: <ConfirmEmailChange/>,
            },
            {
                path: "profile/confirm-email-address/:token",
                element: <ConfirmEmailAddress/>,
            },
        ]
    },
    {
        path: "welcome",
        element: <DefaultLayout/>,
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                element: <Welcome/>,
            },
        ]
    },
    {
        path: "account",
        errorElement: <ErrorPage/>,
        element: <DefaultLayout/>,
        children: [
            {
                path: "",
                element: <ManageAccount/>,
                children: [
                    {
                        path: "settings",
                        element: <AccountSettings/>,
                    },
                    {
                        path: "taxes-and-fees",
                        element: <TaxSettings/>,
                    },
                    {
                        path: "event-defaults",
                        element: <EventDefaultsSettings/>,
                    },
                    {
                        path: "users",
                        element: <Users/>,
                    },
                    {
                        path: "billing",
                        element: <PaymentSettings/>,
                    },
                    {
                        path: "payment",
                        element: <PaymentSettings/>,
                    },
                ]
            },
        ]
    },
    {
        path: "auth",
        element: <AuthLayout/>,
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "login",
                element: <Login/>,
            },
            {
                path: "register",
                element: <Register/>,
            },
            {
                path: "forgot-password",
                element: <ForgotPassword/>,
            },
            {
                path: "reset-password/:token",
                element: <Index/>,
            },
            {
                path: "accept-invitation/:token",
                element: <AcceptInvitation/>,
            }
        ]
    },
    {
        path: "/manage/event/:eventId",
        element: <EventLayout/>,
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                element: <EventDashboard/>,
            },
            {
                path: "dashboard",
                element: <EventDashboard/>,
            },
            {
                path: "tickets",
                element: <Tickets/>,
            },
            {
                path: "attendees",
                element: <Attendees/>,
            },
            {
                path: "questions",
                element: <Questions/>,
            },
            {
                path: "orders",
                element: <Orders/>,
            },
            {
                path: "promo-codes",
                element: <PromoCodes/>,
            },
            {
                path: "affiliates",
                element: <Affiliates/>,
            },
            {
                path: "check-in",
                element: <CheckIn/>,
            },
            {
                path: "messages",
                element: <Messages/>,
            },
            {
                path: "settings",
                element: <Settings/>,
            },
            {
                path: "widget",
                element: <Widget/>,
            },
            {
                path: "homepage-designer",
                element: <HomepageDesigner/>,
            },
            {
                path: "getting-started",
                element: <GettingStarted/>,
            }
        ]
    },
    {
        path: "/event/:eventId/:eventSlug",
        element: <EventHomepage/>,
        errorElement: <ErrorPage/>,
    },
    {
        path: "/widget/:eventId",
        element: <TicketWidget/>,
        errorElement: <ErrorPage/>,
        children: [
            {
                path: "",
                element: <SelectTickets/>
            },
        ]
    },
    {
        path: "/checkout/:eventId",
        element: <Checkout/>,
        errorElement: <ErrorPage/>,
        children: [
            {
                path: ":orderShortId/details",
                element: <CollectInformation/>
            },
            {
                path: ":orderShortId/payment",
                element: <Payment/>
            },
            {
                path: ":orderShortId/summary",
                element: <OrderSummaryAndTickets/>
            },
            {
                path: ":orderShortId/payment_return",
                element: <PaymentReturn/>
            },
        ]
    },
    {
        path: "/order/:eventId/:orderShortId/print",
        element: <PrintOrder/>,
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId/print",
        element: <PrintTicket/>,
        errorElement: <ErrorPage/>
    },
    {
        path: "/ticket/:eventId/:attendeeShortId",
        element: <AttendeeTicketAndInformation/>,
        errorElement: <ErrorPage/>
    },
], {
    future: {
        v7_normalizeFormMethod: true,
    }
});