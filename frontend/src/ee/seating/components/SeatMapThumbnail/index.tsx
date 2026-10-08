import {useMemo} from "react";
import {SeatMapLayout} from "../lib/types.ts";
import {withGeneratedSeats} from "../lib/generateSeats.ts";
import {SeatMapRenderer} from "../SeatMapRenderer";

export const SeatMapThumbnail = ({layout}: {layout: SeatMapLayout}) => {
    const drawable = useMemo(() => withGeneratedSeats(layout), [layout]);
    const bands = useMemo(() => new Map(drawable.bands.map(band => [band.key, band])), [drawable]);

    return <SeatMapRenderer area={drawable.areas[0]} bands={bands}/>;
};
