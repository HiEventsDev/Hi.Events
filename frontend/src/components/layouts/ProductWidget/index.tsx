import {useLocation, useParams} from "react-router";
import '../../../styles/widget/default.scss';
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import {useMemo} from "react";
import {Loader} from "@mantine/core";
import {t} from "@lingui/macro";

const ProductWidget = () => {
    const {eventId} = useParams();
    const location = useLocation();

    // Forward `?occurrence_id=` from the embed URL so the public payload
    // filters products by that occurrence and the storefront preselects it.
    // Without this, embed share links to a specific date show ambiguous
    // pickers and recurring products get the event-wide visibility set.
    const eventOccurrenceId = useMemo(() => {
        const raw = new URLSearchParams(location.search).get('occurrence_id');
        if (raw === null) return null;
        const parsed = Number(raw);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
    }, [location.search]);

    const checkoutMode = useMemo(() => {
        return new URLSearchParams(location.search).get('Checkout') === 'new-tab' ? 'new-tab' : 'modal';
    }, [location.search]);

    const eventQuery = useGetEventPublic(eventId, true, false, null, eventOccurrenceId);

    const settings = useMemo(() => {
        const searchParams = new URLSearchParams(location.search);

        return {
            colors: {
                background: searchParams.get("BackgroundColor") || '#ffffff',
                primary: searchParams.get("PrimaryColor") || '#7b5db8',
                primaryText: searchParams.get("PrimaryTextColor") || '#000000',
                secondary: searchParams.get("SecondaryColor") || '#7b5eb9',
                secondaryText: searchParams.get("SecondaryTextColor") || '#ffffff',
                bodyBackground: searchParams.get("BackgroundColor") || '#ffffff',
            },
            continueButtonText: searchParams.get("ContinueButtonText") || 'Continue',
            padding: searchParams.get("Padding") || '10px',
        };
    }, [location.search]);

    if (eventQuery.isError || (eventQuery.isFetched && !eventQuery.data)) {
        return (
            <div style={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                height: '100vh',
                padding: '20px',
                textAlign: 'center',
                backgroundColor: settings.colors.background,
                color: settings.colors.primaryText,
            }}>
                <div>
                    <p style={{fontWeight: 600, margin: '0 0 4px'}}>{t`This event is not available`}</p>
                    <p style={{margin: 0, opacity: 0.7}}>{t`It may have been unpublished or removed. Please check the link and try again.`}</p>
                </div>
            </div>
        )
    }

    if (!eventQuery.isFetched || !eventQuery.data) {
        return (
            <div style={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                height: '100vh',
                backgroundColor: settings.colors.background
            }}>
                <Loader color={settings.colors.primaryText} size="md" type="dots"/>
            </div>
        )
    }

    return (
        <div className={'full-height'} style={{backgroundColor: settings.colors.bodyBackground}}>
            <SelectProducts
                widgetMode={'embedded'}
                checkoutMode={checkoutMode}
                event={eventQuery.data}
                colors={settings.colors}
                continueButtonText={settings.continueButtonText}
                padding={settings.padding}
            />
        </div>
    );
};

export default ProductWidget;
