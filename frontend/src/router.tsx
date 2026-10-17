import { Navigate, RouteObject } from "react-router";
import ErrorPage from "./error-page.tsx";
import { useEffect, useState } from "react";
import { useGetMe } from "./queries/useGetMe.ts";
import { publicEventRouteLoader } from "./routeLoaders/publicEventRouteLoader.ts";
import { publicOrganizerRouteLoader } from "./routeLoaders/publicOrganizerRouteLoader.ts";
import { organizerPreviewRouteLoader } from "./routeLoaders/organizerPreviewRouteLoader.ts";
import { authRouteLoader } from "./routeLoaders/authRouteLoader.ts";
import { eventContentWidgetRouteLoader, organizerWidgetRouteLoader, ticketWidgetRouteLoader } from "./routeLoaders/widgetRouteLoader.ts";

const Root = () => {
    const [redirectPath, setRedirectPath] = useState<string | null>(null);
    const me = useGetMe();

    useEffect(() => {
        if (me.isFetched) {
            const searchParams = typeof window !== 'undefined' ? window.location.search : '';
            const basePath = me.isSuccess ? "/manage/events" : "/auth/login";
            setRedirectPath(basePath + searchParams);
        }
    }, [me.isFetched]);

    if (redirectPath) {
        return <Navigate to={redirectPath} replace={true} />;
    }
};

export const router: RouteObject[] = [
    {
        path: "",
        element: <Root />,
        errorElement: <ErrorPage />
    },
    {
        path: "auth",
        loader: authRouteLoader,
        async lazy() {
            const AuthLayout = await import("./components/layouts/AuthLayout");
            return { Component: AuthLayout.default };
        },
        errorElement: <ErrorPage />,
        children: [
            {
                path: "login",
                async lazy() {
                    const Login = await import("./components/routes/auth/Login");
                    return { Component: Login.default };
                },
            },
            {
                path: "register",
                async lazy() {
                    const Register = await import("./components/routes/auth/Register");
                    return { Component: Register.default };
                }
            },
            {
                path: "forgot-password",
                async lazy() {
                    const ForgotPassword = await import("./components/routes/auth/ForgotPassword");
                    return { Component: ForgotPassword.default };
                }
            },
            {
                path: "reset-password/:token",
                async lazy() {
                    const ResetPassword = await import("./components/routes/auth/ResetPassword");
                    return { Component: ResetPassword.default };
                }
            },
            {
                path: "accept-invitation/:token",
                async lazy() {
                    const AcceptInvitation = await import("./components/routes/auth/AcceptInvitation");
                    return { Component: AcceptInvitation.default };
                }
            },
            {
                path: "two-factor-setup",
                async lazy() {
                    const RequiredTwoFactorSetup = await import("./components/routes/auth/RequiredTwoFactorSetup");
                    return { Component: RequiredTwoFactorSetup.default };
                }
            }
        ]
    },
    {
        path: "sso/callback",
        async lazy() {
            const SsoCallback = await import("./components/routes/sso/Callback");
            return { Component: SsoCallback.default };
        }
    },
    {
        path: "manage",
        errorElement: <ErrorPage />,
        async lazy() {
            const DefaultLayout = await import("./components/layouts/DefaultLayout");
            return { Component: DefaultLayout.default };
        },
        children: [
            {
                path: "events/:eventsState?",
                async lazy() {
                    const Dashboard = await import("./components/routes/events/Dashboard");
                    return { Component: Dashboard.default };
                },
            },
            {
                path: "account",
                element: <Navigate to="/settings/account" replace={true} />
            },
            {
                path: "profile",
                element: <Navigate to="/settings/profile" replace={true} />
            },
            {
                path: "profile/security",
                element: <Navigate to="/settings/security" replace={true} />
            },
            {
                path: "profile/password",
                element: <Navigate to="/settings/security" replace={true} />
            },
            {
                path: "profile/confirm-email-change/:token",
                async lazy() {
                    const ConfirmEmailChange = await import("./components/routes/profile/ConfirmEmailChange");
                    return { Component: ConfirmEmailChange.default };
                }
            },
            {
                path: "profile/confirm-email-address/:token",
                async lazy() {
                    const ConfirmEmailAddress = await import("./components/routes/profile/ConfirmEmailAddress");
                    return { Component: ConfirmEmailAddress.default };
                }
            },
        ]
    },
    {
        path: "welcome",
        async lazy() {
            const WelcomeLayout = await import("./components/layouts/WelcomeLayout");
            return { Component: WelcomeLayout.default };
        },
        errorElement: <ErrorPage />,
        children: [
            {
                path: "",
                async lazy() {
                    const Welcome = await import("./components/routes/welcome");
                    return { Component: Welcome.default };
                }
            },
        ]
    },
    {
        path: "admin",
        errorElement: <ErrorPage />,
        async lazy() {
            const AdminLayout = await import("./components/layouts/Admin");
            return { Component: AdminLayout.default };
        },
        children: [
            {
                path: "",
                async lazy() {
                    const Dashboard = await import("./components/routes/admin/Dashboard");
                    return { Component: Dashboard.default };
                }
            },
            {
                path: "accounts",
                async lazy() {
                    const Accounts = await import("./components/routes/admin/Accounts");
                    return { Component: Accounts.default };
                }
            },
            {
                path: "accounts/:accountId",
                async lazy() {
                    const AccountDetail = await import("./components/routes/admin/Accounts/AccountDetail");
                    return { Component: AccountDetail.default };
                }
            },
            {
                path: "deletion-requests",
                async lazy() {
                    const DeletionRequests = await import("./components/routes/admin/DeletionRequests");
                    return { Component: DeletionRequests.default };
                }
            },
            {
                path: "users",
                async lazy() {
                    const Users = await import("./components/routes/admin/Users");
                    return { Component: Users.default };
                }
            },
            {
                path: "events",
                async lazy() {
                    const Events = await import("./components/routes/admin/Events");
                    return { Component: Events.default };
                }
            },
            {
                path: "orders",
                async lazy() {
                    const Orders = await import("./components/routes/admin/Orders");
                    return { Component: Orders.default };
                }
            },
            {
                path: "attribution",
                async lazy() {
                    const Attribution = await import("./components/routes/admin/Attribution");
                    return { Component: Attribution.default };
                }
            },
            {
                path: "configurations",
                async lazy() {
                    const Configurations = await import("./components/routes/admin/Configurations");
                    return { Component: Configurations.default };
                }
            },
            {
                path: "feature-flags",
                async lazy() {
                    const FeatureFlags = await import("./components/routes/admin/FeatureFlags");
                    return { Component: FeatureFlags.default };
                }
            },
            {
                path: "licence",
                async lazy() {
                    const Licence = await import("./ee/licensing/components/routes/admin/Licence");
                    return { Component: Licence.default };
                }
            },
            {
                path: "email-delivery",
                async lazy() {
                    const EmailDelivery = await import("./components/routes/admin/EmailDelivery");
                    return { Component: EmailDelivery.default };
                }
            },
            {
                path: "failed-jobs",
                async lazy() {
                    const FailedJobs = await import("./components/routes/admin/FailedJobs");
                    return { Component: FailedJobs.default };
                }
            },
            {
                path: "messages",
                async lazy() {
                    const Messages = await import("./components/routes/admin/Messages");
                    return { Component: Messages.default };
                }
            },
            {
                path: "spam-events",
                async lazy() {
                    const SpamEvents = await import("./components/routes/admin/SpamEvents");
                    return { Component: SpamEvents.default };
                }
            },
            {
                path: "announcements",
                async lazy() {
                    const Announcements = await import("./components/routes/admin/Announcements");
                    return { Component: Announcements.default };
                }
            },
            {
                path: "lifecycle-emails",
                async lazy() {
                    const LifecycleEmails = await import("./components/routes/admin/LifecycleEmails");
                    return { Component: LifecycleEmails.default };
                }
            }
        ]
    },
    {
        path: "settings",
        errorElement: <ErrorPage />,
        async lazy() {
            const SettingsLayout = await import("./components/layouts/SettingsLayout");
            return { Component: SettingsLayout.default };
        },
        children: [
            {
                index: true,
                element: <Navigate to="/settings/profile" replace={true} />
            },
            {
                path: "profile",
                async lazy() {
                    const Profile = await import("./components/routes/settings/Profile");
                    return { Component: Profile.default };
                }
            },
            {
                path: "security",
                async lazy() {
                    const Security = await import("./components/routes/settings/Security");
                    return { Component: Security.default };
                }
            },
            {
                path: "account",
                async lazy() {
                    const Account = await import("./components/routes/settings/Account");
                    return { Component: Account.default };
                }
            },
            {
                path: "organizers",
                async lazy() {
                    const Organizers = await import("./components/routes/settings/Organizers");
                    return { Component: Organizers.default };
                }
            },
            {
                path: "team",
                async lazy() {
                    const Team = await import("./components/routes/settings/Team");
                    return { Component: Team.default };
                }
            },
            {
                path: "api-keys",
                async lazy() {
                    const ApiKeys = await import("./components/routes/settings/ApiKeys");
                    return { Component: ApiKeys.default };
                }
            },
            {
                path: "api-keys/reference",
                async lazy() {
                    const ApiReference = await import("./components/routes/settings/ApiReference");
                    return { Component: ApiReference.default };
                }
            },
            {
                path: "taxes-and-fees",
                async lazy() {
                    const TaxesAndFees = await import("./components/routes/settings/TaxesAndFees");
                    return { Component: TaxesAndFees.default };
                }
            },
        ]
    },
    {
        path: "account",
        children: [
            { path: "users", element: <Navigate to="/settings/team" replace={true} /> },
            { path: "taxes-and-fees", element: <Navigate to="/settings/taxes-and-fees" replace={true} /> },
            { path: "danger-zone", element: <Navigate to="/settings/account#delete-account" replace={true} /> },
            { path: "*", element: <Navigate to="/settings/account" replace={true} /> },
            { index: true, element: <Navigate to="/settings/account" replace={true} /> },
        ]
    },
    {
        path: "/manage/organizer/:organizerId?",
        async lazy() {
            const Dashboard = await import("./components/layouts/OrganizerLayout");
            return { Component: Dashboard.default };
        },
        errorElement: <ErrorPage />,
        children: [
            {
                path: "dashboard?",
                async lazy() {
                    const OrganizerDashboard = await import("./components/routes/organizer/OrganizerDashboard");
                    return { Component: OrganizerDashboard.default };
                }
            },
            {
                path: "events/:eventsState?",
                async lazy() {
                    const Events = await import("./components/routes/organizer/Events");
                    return { Component: Events.default };
                }
            },
            {
                path: "settings",
                async lazy() {
                    const Settings = await import("./components/routes/organizer/Settings");
                    return { Component: Settings.default };
                }
            },
            {
                path: "organizer-homepage-designer",
                async lazy() {
                    const OrganizerHomepageDesigner = await import("./components/routes/organizer/OrganizerHomepageDesigner");
                    return { Component: OrganizerHomepageDesigner.default };
                }
            },
            {
                path: "widget",
                async lazy() {
                    const OrganizerWidget = await import("./components/routes/organizer/OrganizerWidget");
                    return { Component: OrganizerWidget.default };
                }
            },
            {
                path: "webhooks",
                async lazy() {
                    const Webhooks = await import("./components/routes/organizer/Webhooks");
                    return { Component: Webhooks.default };
                }
            },
            {
                path: "team",
                async lazy() {
                    const OrganizerTeam = await import("./components/routes/organizer/Team");
                    return { Component: OrganizerTeam.default };
                }
            },
            {
                path: "locations",
                async lazy() {
                    const Locations = await import("./components/routes/organizer/Locations");
                    return { Component: Locations.default };
                }
            },
            {
                path: "people",
                async lazy() {
                    const People = await import("./components/routes/organizer/People");
                    return { Component: People.default };
                }
            },
            {
                path: "seat-maps",
                async lazy() {
                    const SeatMaps = await import("./ee/seating/components/routes/organizer/SeatMaps");
                    return { Component: SeatMaps.default };
                }
            },
            {
                path: "seat-maps/:seatMapId",
                async lazy() {
                    const SeatMapDesigner = await import("./ee/seating/components/routes/organizer/SeatMapDesigner");
                    return { Component: SeatMapDesigner.default };
                }
            },
            {
                path: "payments",
                async lazy() {
                    const PaymentsRedirect = await import("./components/routes/organizer/Payments/Redirect");
                    return { Component: PaymentsRedirect.default };
                }
            },
            {
                path: "reports",
                async lazy() {
                    const OrganizerReports = await import("./components/routes/organizer/Reports");
                    return { Component: OrganizerReports.default };
                }
            },
            {
                path: "report/:reportType",
                async lazy() {
                    const OrganizerReportLayout = await import("./components/routes/organizer/Reports/ReportLayout");
                    return { Component: OrganizerReportLayout.default };
                }
            }
        ],
    },
    {
        path: "/manage/event/:eventId",
        async lazy() {
            const EventLayout = await import("./components/layouts/Event");
            return { Component: EventLayout.default };
        },
        errorElement: <ErrorPage />,
        children: [
            {
                path: "",
                async lazy() {
                    const EventDashboard = await import("./components/routes/event/EventDashboard");
                    return { Component: EventDashboard.default };
                }
            },
            {
                path: "dashboard",
                async lazy() {
                    const EventDashboard = await import("./components/routes/event/EventDashboard");
                    return { Component: EventDashboard.default };
                }
            },
            {
                path: "getting-started",
                element: <Navigate to="../dashboard" replace={true} />
            },
            {
                path: "reports",
                async lazy() {
                    const Reports = await import("./components/routes/event/Reports");
                    return { Component: Reports.default };
                },
            },
            {
                path: "report/:reportType",
                async lazy() {
                    const ReportLayout = await import("./components/routes/event/Reports/ReportLayout");
                    return { Component: ReportLayout.default };
                },
            },
            {
                path: "products",
                async lazy() {
                    const Products = await import("./components/routes/event/products");
                    return { Component: Products.default };
                }
            },
            {
                path: "attendees",
                async lazy() {
                    const Attendees = await import("./components/routes/event/attendees");
                    return { Component: Attendees.default };
                }
            },
            {
                path: "questions",
                async lazy() {
                    const Questions = await import("./components/routes/event/questions");
                    return { Component: Questions.default };
                }
            },
            {
                path: "orders",
                async lazy() {
                    const Orders = await import("./components/routes/event/orders");
                    return { Component: Orders.default };
                }
            },
            {
                path: "promo-codes",
                async lazy() {
                    const PromoCodes = await import("./components/routes/event/promo-codes");
                    return { Component: PromoCodes.default };
                }
            },
            {
                path: "affiliates",
                async lazy() {
                    const Affiliates = await import("./components/routes/event/Affiliates");
                    return { Component: Affiliates.default };
                }
            },
            {
                path: "lineup",
                async lazy() {
                    const Lineup = await import("./components/routes/event/Lineup");
                    return { Component: Lineup.default };
                }
            },
            {
                path: "check-in",
                async lazy() {
                    const CheckIn = await import("./components/routes/event/CheckInLists");
                    return { Component: CheckIn.default };
                }
            },
            {
                path: "box-office",
                async lazy() {
                    const BoxOffices = await import("./ee/box-office/components/routes/event/BoxOffices");
                    return { Component: BoxOffices.default };
                }
            },
            {
                path: "messages",
                async lazy() {
                    const Messages = await import("./components/routes/event/messages");
                    return { Component: Messages.default };
                }
            },
            {
                path: "settings",
                async lazy() {
                    const Settings = await import("./components/routes/event/Settings");
                    return { Component: Settings.default };
                }
            },
            {
                path: "widget",
                async lazy() {
                    const Widget = await import("./components/routes/event/widget");
                    return { Component: Widget.default };
                }
            },
            {
                path: "homepage-designer",
                async lazy() {
                    const HomepageDesigner = await import("./components/routes/event/HomepageDesigner");
                    return { Component: HomepageDesigner.default };
                }
            },
            {
                path: "ticket-designer",
                async lazy() {
                    const TicketDesigner = await import("./components/routes/event/TicketDesigner");
                    return { Component: TicketDesigner.default };
                }
            },
            {
                path: "sold-out-waitlist",
                async lazy() {
                    const SoldOutWaitlist = await import("./components/routes/event/SoldOutWaitlist");
                    return { Component: SoldOutWaitlist.default };
                }
            },
            {
                path: "occurrences",
                async lazy() {
                    const OccurrencesTab = await import("./components/routes/event/OccurrencesTab");
                    return {Component: OccurrencesTab.default};
                }
            },
            {
                path: "occurrences/calendar",
                async lazy() {
                    const OccurrencesTab = await import("./components/routes/event/OccurrencesTab");
                    return {Component: OccurrencesTab.default};
                }
            },
            {
                path: "occurrences/:occurrenceId",
                async lazy() {
                    const OccurrenceDetail = await import("./components/routes/event/OccurrenceDetail");
                    return {Component: OccurrenceDetail.default};
                }
            },
            {
                path: "capacity-assignments",
                async lazy() {
                    const CapacityAssignments = await import("./components/routes/event/CapacityAssignments");
                    return { Component: CapacityAssignments.default };
                }
            },
            {
                path: "seating",
                async lazy() {
                    const Seating = await import("./ee/seating/components/routes/event/Seating");
                    return { Component: Seating.default };
                }
            },
            {
                path: "seating/sales",
                async lazy() {
                    const SeatingSales = await import("./ee/seating/components/routes/event/SeatingSales");
                    return { Component: SeatingSales.default };
                }
            },
            {
                path: "seating/designer",
                async lazy() {
                    const SeatMapDesigner = await import("./ee/seating/components/routes/event/SeatMapDesigner");
                    return { Component: SeatMapDesigner.default };
                }
            },
            {
                path: "webhooks",
                async lazy() {
                    const Webhooks = await import("./components/routes/event/Webhooks");
                    return { Component: Webhooks.default };
                }
            },
            {
                path: "team",
                async lazy() {
                    const EventTeam = await import("./components/routes/event/Team");
                    return { Component: EventTeam.default };
                }
            }
        ]
    },
    {
        path: "/events/:organizerId/:organizerSlug",
        loader: publicOrganizerRouteLoader,
        async lazy() {
            const PublicOrganizer = await import("./components/layouts/PublicOrganizer");
            return { Component: PublicOrganizer.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/events/:organizerId/:organizerSlug/past-events",
        loader: publicOrganizerRouteLoader,
        async lazy() {
            const PublicOrganizer = await import("./components/layouts/PublicOrganizer");
            return { Component: PublicOrganizer.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/e/:eventId/:eventSlug",
        async lazy() {
            const EventHomepage = await import("./components/layouts/EventHomepage");
            return { Component: EventHomepage.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/event/:eventId/preview",
        async lazy() {
            const EventHomepagePreview = await import("./components/layouts/EventHomepagePreview");
            return { Component: EventHomepagePreview.default };
        },
    },
    {
        path: "/organizer/:organizerId/preview",
        loader: organizerPreviewRouteLoader,
        async lazy() {
            const OrganizerHomepagePreview = await import("./components/layouts/OrganizerHomepagePreview");
            return { Component: OrganizerHomepagePreview.default };
        },
    },
    {
        path: "/event/:eventId/:eventSlug",
        loader: publicEventRouteLoader,
        async lazy() {
            const PublicEvent = await import("./components/layouts/PublicEvent");
            return { Component: PublicEvent.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/widget/organizer/:organizerId",
        loader: organizerWidgetRouteLoader,
        async lazy() {
            const OrganizerEventsWidget = await import("./components/layouts/OrganizerEventsWidget");
            return { Component: OrganizerEventsWidget.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/widget/:eventId",
        loader: ticketWidgetRouteLoader,
        async lazy() {
            const ProductWidget = await import("./components/layouts/ProductWidget");
            return { Component: ProductWidget.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/widget/:eventId/lineup",
        loader: eventContentWidgetRouteLoader,
        async lazy() {
            const {LineupWidget} = await import("./components/layouts/EventContentWidget");
            return { Component: LineupWidget };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/widget/:eventId/schedule",
        loader: eventContentWidgetRouteLoader,
        async lazy() {
            const {ScheduleWidget} = await import("./components/layouts/EventContentWidget");
            return { Component: ScheduleWidget };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/checkout/:eventId/seats",
        async lazy() {
            const SeatCheckout = await import("./ee/seating/components/layouts/SeatCheckout");
            return { Component: SeatCheckout.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/checkout/:eventId",
        async lazy() {
            const Checkout = await import("./components/layouts/Checkout");
            return { Component: Checkout.default };
        },
        errorElement: <ErrorPage />,
        children: [
            {
                path: ":orderShortId/details",
                async lazy() {
                    const CollectInformation = await import("./components/routes/product-widget/CollectInformation");
                    return { Component: CollectInformation.default };
                }
            },
            {
                path: ":orderShortId/payment",
                async lazy() {
                    const Payment = await import("./components/routes/product-widget/Payment");
                    return { Component: Payment.default };
                }
            },
            {
                path: ":orderShortId/summary",
                async lazy() {
                    const OrderSummaryAndProducts = await import("./components/routes/product-widget/OrderSummaryAndProducts");
                    return { Component: OrderSummaryAndProducts.default };
                }
            },
            {
                path: ":orderShortId/payment_return",
                async lazy() {
                    const PaymentReturn = await import("./components/routes/product-widget/PaymentReturn");
                    return { Component: PaymentReturn.default };
                }
            },
        ]
    },
    {
        path: "/order/:eventId/:orderShortId/print",
        async lazy() {
            const PrintOrder = await import("./components/routes/product-widget/PrintOrder");
            return { Component: PrintOrder.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/product/:eventId/:attendeeShortId/print",
        async lazy() {
            const PrintProduct = await import("./components/routes/product-widget/PrintProduct");
            return { Component: PrintProduct.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/manage/event/:eventId/ticket-designer/print",
        async lazy() {
            const TicketDesignerPrint = await import("./components/routes/event/TicketDesigner/TicketDesignerPrint");
            return { Component: TicketDesignerPrint.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/manage/event/:eventId/lineup/print",
        async lazy() {
            const RunSheetPrint = await import("./components/routes/event/Lineup/RunSheetPrint");
            return { Component: RunSheetPrint.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/manage/event/:eventId/seating/print",
        async lazy() {
            const SeatingPrint = await import("./ee/seating/components/routes/event/SeatingPrint");
            return { Component: SeatingPrint.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/product/:eventId/:attendeeShortId",
        async lazy() {
            const AttendeeProductAndInformation = await import("./components/routes/product-widget/AttendeeProductAndInformation");
            return { Component: AttendeeProductAndInformation.default };
        },
        errorElement: <ErrorPage />
    },
    {
        path: "/check-in/:checkInListShortId",
        async lazy() {
            const CheckIn = await import("./components/layouts/CheckIn");
            return { Component: CheckIn.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/box-office/:boxOfficeShortId",
        async lazy() {
            const BoxOffice = await import("./ee/box-office/components/layouts/BoxOffice");
            return { Component: BoxOffice.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/emails/unsubscribe/:token",
        async lazy() {
            const Unsubscribe = await import("./components/routes/emails/Unsubscribe");
            return { Component: Unsubscribe.default };
        },
        errorElement: <ErrorPage />,
    },
    {
        path: "/my-tickets/:token",
        async lazy() {
            const MyTickets = await import("./components/routes/my-tickets");
            return { Component: MyTickets.default };
        },
        errorElement: <ErrorPage />,
    }
];

