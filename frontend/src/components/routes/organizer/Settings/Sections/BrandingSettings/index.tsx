import {t} from "@lingui/macro";
import {Link, useParams} from "react-router";
import {useEffect, useState} from "react";
import {Anchor, Switch, Text} from "@mantine/core";
import {IconCircleCheck} from "@tabler/icons-react";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {FeatureGate} from "../../../../../common/FeatureGate";
import {useOrganizerPlan} from "../../../../../../hooks/useOrganizerPlan.ts";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {getConfig} from "../../../../../../utilites/config.ts";
import classes from "./BrandingSettings.module.scss";

export const BrandingSettings = () => {
    const {organizerId} = useParams();
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");
    const {data: organizer} = useGetOrganizer(organizerId);
    const {hasFeature} = useOrganizerPlan(organizerId);
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();

    const [hideBranding, setHideBranding] = useState(false);

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            setHideBranding(organizerSettingsQuery.data.hide_branding ?? false);
        }
    }, [organizerSettingsQuery.isFetched, organizerSettingsQuery.data]);

    const entitled = hasFeature('branding_removal');
    const organizerLogo = organizer?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const hasLogo = !!organizerLogo;
    const designerUrl = `/manage/organizer/${organizerId}/organizer-homepage-designer`;
    const organizerName = organizer?.name ?? '';
    const organizerInitial = organizerName.charAt(0).toUpperCase();

    const saveBrandingPreference = (value: boolean) => {
        updateMutation.mutate({
            organizerSettings: {hide_branding: value},
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                setHideBranding(value);
                showSuccess(value ? t`${appName} branding removed` : t`${appName} branding restored`);
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message ?? t`Failed to update branding settings`);
            },
        });
    };

    return (
        <Card>
            <div className={classes.layout}>
                <div className={classes.content}>
                    <HeadingWithDescription
                        heading={t`Make it yours`}
                        description={t`Put your brand front and center, everywhere your attendees look.`}
                    />

                    <FeatureGate
                        organizerId={organizerId}
                        feature="branding_removal"
                        teaser={(
                            <>
                                <ul className={classes.benefits}>
                                    <li>
                                        <IconCircleCheck size={18}/>
                                        {t`Your logo on every attendee email`}
                                    </li>
                                    <li>
                                        <IconCircleCheck size={18}/>
                                        {t`No "Powered by ${appName}" on your event page, checkout or tickets`}
                                    </li>
                                    <li>
                                        <IconCircleCheck size={18}/>
                                        {t`A seamless, fully-branded experience for your attendees`}
                                    </li>
                                </ul>
                                <Text size="xs" c="dimmed">
                                    {t`Applies to paid events — free events always include ${appName} branding.`}
                                </Text>
                            </>
                        )}
                    >
                        <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                            <Switch
                                checked={hideBranding}
                                onChange={(event) => saveBrandingPreference(event.currentTarget.checked)}
                                label={t`Remove ${appName} branding`}
                                description={t`Included in your plan. Applies to paid events — free events always include ${appName} branding.`}
                            />

                            <Text size="sm" c="dimmed" mt="md">
                                {hasLogo
                                    ? t`Emails use the organizer logo from your Homepage Designer.`
                                    : t`You haven't added an organizer logo yet — add one so your emails are fully branded.`}
                                {' '}
                                <Anchor component={Link} to={designerUrl}>
                                    {hasLogo ? t`Change logo` : t`Add a logo`}
                                </Anchor>
                            </Text>
                        </fieldset>
                    </FeatureGate>
                </div>

                <div className={classes.previewPanel}>
                    <div className={classes.emailStack}>
                        <div className={classes.emailWindow} data-branded={(entitled && hideBranding) || undefined}>
                            <div className={classes.windowBar}><i/><i/><i/></div>
                            <div className={classes.emailHeader}>
                                <img
                                    src={getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-light.svg")}
                                    className={classes.platformLogo}
                                    alt=""
                                />
                                {hasLogo
                                    ? (
                                        <img
                                            src={organizerLogo?.url}
                                            className={classes.organizerLogo}
                                            alt=""
                                        />
                                    )
                                    : (
                                        <div className={`${classes.organizerLogo} ${classes.organizerMark}`}>
                                            <span>{organizerInitial}</span>
                                            {organizerName}
                                        </div>
                                    )}
                            </div>
                            <div className={classes.emailBody}>
                                <div className={classes.subject}>{t`Your Order is Confirmed!`} 🎉</div>
                                <div className={classes.line} style={{width: '100%'}}/>
                                <div className={classes.line} style={{width: '82%'}}/>
                                <div className={classes.line} style={{width: '58%'}}/>
                                <div className={classes.cta}>{t`View Order`}</div>
                            </div>
                            <div className={classes.emailFooter}>
                                <span className={classes.poweredBy}>{t`Powered by`} {appName} 🚀</span>
                                <span className={classes.organizerFooter}>{organizerName}</span>
                            </div>
                        </div>
                    </div>
                    <p className={classes.previewCaption}>
                        {entitled && hideBranding
                            ? t`Your attendee emails, fully branded`
                            : t`Preview: your emails with ${appName} branding removed`}
                    </p>
                </div>
            </div>
        </Card>
    );
}

export default BrandingSettings;
