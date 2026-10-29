import {EventFeature, IdParam} from "../types.ts";
import {useGetEventSettings} from "../queries/useGetEventSettings.ts";

export const useEventFeature = (eventId: IdParam, feature: EventFeature): boolean => {
    const {data: settings} = useGetEventSettings(eventId);

    return !!settings?.enabled_features?.includes(feature);
};
