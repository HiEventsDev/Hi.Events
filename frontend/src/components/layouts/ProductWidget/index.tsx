import {useLocation, useParams} from "react-router";
import '../../../styles/widget/default.scss';
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import {useMemo} from "react";
import {Loader} from "@mantine/core";

const ProductWidget = () => {
    const {eventId} = useParams();
    const location = useLocation();
    const eventQuery = useGetEventPublic(eventId);

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
                event={eventQuery.data}
                colors={settings.colors}
                continueButtonText={settings.continueButtonText}
                padding={settings.padding}
            />
        </div>
    );
};

export default ProductWidget;
