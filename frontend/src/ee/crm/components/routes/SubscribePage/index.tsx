import {t} from "@lingui/macro";
import {Helmet} from "react-helmet-async";
import {useLoaderData, useParams} from "react-router";
import {Button, Loader} from "@mantine/core";
import {IconAlertCircle} from "@tabler/icons-react";
import {Card} from "../../../../../components/common/Card";
import {PoweredByFooter} from "../../../../../components/common/PoweredByFooter";
import {WidgetLoaderData} from "../../../../../routeLoaders/widgetRouteLoader.ts";
import {apiErrorStatus} from "../../../../../utilites/apiErrors.ts";
import {useGetSignupFormPublic} from "../../../queries/useCrmSignupFormQueries.ts";
import {CrmSignupFormPublic} from "../../../types.ts";
import {SignupFormCard} from "../../SignupFormCard";
import {StatusHeading} from "./StatusPage.tsx";
import classes from "./Subscribe.module.scss";

const SubscribeDocumentHead = ({form}: {form: CrmSignupFormPublic}) => {
    const organizerName = form.organizer_name ?? '';
    const heading = form.title || t`Get updates`;
    const title = organizerName ? `${heading} · ${organizerName}` : heading;
    const description = form.description
        || (organizerName ? t`Sign up for email updates from ${organizerName}.` : t`Sign up for email updates.`);

    return (
        <Helmet>
            <title>{title}</title>
            <meta name="description" content={description}/>
            <meta property="og:title" content={title}/>
            <meta property="og:description" content={description}/>
            <meta property="og:type" content="website"/>
            <meta name="twitter:title" content={title}/>
            <meta name="twitter:description" content={description}/>
            <meta name="twitter:card" content="summary"/>
        </Helmet>
    );
};

const SubscribePage = () => {
    const {publicId} = useParams();
    const {notFound} = useLoaderData() as WidgetLoaderData;
    const formQuery = useGetSignupFormPublic(publicId, !notFound);
    const form = formQuery.data;

    const content = () => {
        if (formQuery.isLoading) {
            return <div className={classes.status}><Loader/></div>;
        }

        if (formQuery.isError && apiErrorStatus(formQuery.error) !== 404) {
            return (
                <div className={classes.status}>
                    <StatusHeading icon={IconAlertCircle} title={t`We couldn't load this form`} tone="error"/>
                    <p>{t`Something went wrong on our side. Please try again.`}</p>
                    <Button onClick={() => formQuery.refetch()} loading={formQuery.isFetching}>{t`Try again`}</Button>
                </div>
            );
        }

        if (!form) {
            return (
                <div className={classes.status}>
                    <StatusHeading icon={IconAlertCircle} title={t`This form isn't available`}/>
                    <p>{t`It may have been paused or removed. Check the link with whoever shared it.`}</p>
                </div>
            );
        }

        return <SignupFormCard form={form} publicId={form.public_id} variant="plain"/>;
    };

    return (
        <div className={classes.container}>
            {form && <SubscribeDocumentHead form={form}/>}
            {form?.organizer_name && (
                form.organizer_slug
                    ? <a className={classes.organizer} href={`/events/${form.organizer_id}/${form.organizer_slug}`}>{form.organizer_name}</a>
                    : <span className={classes.organizer}>{form.organizer_name}</span>
            )}
            <Card>{content()}</Card>
            <PoweredByFooter/>
        </div>
    );
};

export default SubscribePage;
