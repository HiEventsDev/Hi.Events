import {useEffect} from "react";
import {useParams, useSearchParams} from "react-router";
import '../../../../../styles/widget/default.scss';
import {useGetEventPublic} from "../../../../../queries/useGetEventPublic.ts";
import {useGetPromoCodePublic} from "../../../../../queries/useGetPromoCodePublic.ts";
import {getParentOrigin} from "../../../../../utilites/iframeResize.ts";
import SelectProducts from "../../../../../components/routes/product-widget/SelectProducts";

const SeatCheckout = () => {
    const {eventId} = useParams();
    const [searchParams] = useSearchParams();
    const occurrenceId = Number(searchParams.get('occurrence_id')) || null;
    const promoCode = searchParams.get('promo_code');
    const promoCodeQuery = useGetPromoCodePublic(eventId, promoCode);
    const isPromoCodeResolved = promoCode === null || promoCodeQuery.isFetched;
    const promoCodeValid = promoCode === null ? undefined : promoCodeQuery.data?.valid === true;
    const event = useGetEventPublic(
        eventId,
        isPromoCodeResolved,
        promoCodeValid === true,
        promoCodeValid ? promoCode : null,
        occurrenceId,
    ).data;

    useEffect(() => {
        const parentOrigin = getParentOrigin();
        const onMessage = (message: MessageEvent) => {
            if (parentOrigin && message.origin !== parentOrigin) return;
            if (message.data?.type === 'hievents:request-close') {
                window.parent.postMessage({type: 'hievents:close-checkout'}, parentOrigin || '*');
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, []);

    if (!event || !isPromoCodeResolved) {
        return null;
    }

    return (
        <SelectProducts event={event} widgetMode="normal" isSeatPickerPage initialOccurrenceId={occurrenceId}
                        promoCode={promoCodeValid ? promoCode ?? undefined : undefined}
                        promoCodeValid={promoCodeValid}/>
    );
};

export default SeatCheckout;
