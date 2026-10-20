import {useState} from "react";
import {UnstyledButton} from "@mantine/core";
import {IconChevronDown} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {SeatMapBand} from "../lib/types.ts";
import {UserGeneratedContent} from "../../../../components/common/UserGeneratedContent";
import {BandUnavailableReason, formatPrice, isBandSoldOut, priceSummary, TicketOption} from "./ticketOptions.ts";
import {SeatedTicketType} from "./useSeatSelection.ts";
import classes from "./SeatPrices.module.scss";

interface SeatPricesProps {
    bands: SeatMapBand[];
    options: Map<string, TicketOption[]>;
    ticketTypes: SeatedTicketType[];
    bandFree: Record<string, number> | undefined;
    unavailableReasons: Record<string, BandUnavailableReason>;
    currency: string;
}

interface PriceRow {
    key: string;
    label: string;
    description: string | null;
    priceId: number | null;
    unavailable: string | null;
}

const reasonLabel = (reason: BandUnavailableReason | undefined): string => {
    switch (reason) {
        case 'not_yet_on_sale':
            return t`Not yet on sale`;
        case 'sales_ended':
            return t`Sales ended`;
        default:
            return t`Sold out`;
    }
};

const rowsFor = (ticketTypes: SeatedTicketType[], options: Map<string, TicketOption[]>): PriceRow[] => {
    const allOptions = [...options.values()].flat();

    return ticketTypes.flatMap(({product}): PriceRow[] => {
        const productOptions = allOptions.filter(option => option.product_id === Number(product.id));
        const priceIds = [...new Set(productOptions.map(option => option.price_id))];

        if (priceIds.length === 0) {
            return [{
                key: `product-${product.id}`,
                label: product.title,
                description: product.description || null,
                priceId: null,
                unavailable: product.is_before_sale_start_date
                    ? t`Not yet on sale`
                    : product.is_after_sale_end_date ? t`Sales ended` : t`Sold out`,
            }];
        }

        return priceIds.map(priceId => {
            const option = productOptions.find(candidate => candidate.price_id === priceId)!;
            return {
                key: `price-${priceId}`,
                label: option.label,
                description: option.description,
                priceId,
                unavailable: null,
            };
        });
    });
};

export const SeatPrices = ({bands, options, ticketTypes, bandFree, unavailableReasons, currency}: SeatPricesProps) => {
    const [showAll, setShowAll] = useState(false);
    const rows = rowsFor(ticketTypes, options);

    if (bands.length === 0 || rows.length === 0) {
        return null;
    }

    const bandStatus = (band: SeatMapBand): {soldOut: boolean; text: string | null} => {
        const free = bandFree?.[band.key];
        const soldOut = isBandSoldOut(options.get(band.key) ?? [], free);
        return {
            soldOut,
            text: soldOut
                ? (free === 0 ? t`Sold out` : reasonLabel(unavailableReasons[band.key]))
                : free !== undefined ? t`${free} left` : null,
        };
    };

    const priceValue = (band: SeatMapBand, row: PriceRow): number | null => {
        const option = (options.get(band.key) ?? []).find(candidate => candidate.price_id === row.priceId);
        return option && !bandStatus(band).soldOut ? option.price : null;
    };

    const bandName = (band: SeatMapBand) => (
        <span className={classes.bandName}>
            <span className={classes.swatch} style={{background: band.color}} aria-hidden/>
            {band.name}
        </span>
    );

    const keyPrice = (band: SeatMapBand): string | null => priceSummary(
        rows.map(row => priceValue(band, row)).filter((price): price is number => price !== null),
        currency,
    );

    return (
        <div className={classes.prices} data-testid="seat-prices">
            <ul className={classes.key}>
                {bands.map(band => {
                    const status = bandStatus(band);
                    const price = keyPrice(band);
                    return (
                        <li key={band.key} className={classes.keyLine} data-testid={`seat-legend-band-${band.key}`}>
                            {bandName(band)}
                            <span className={classes.availability}>{status.text}</span>
                            {!status.soldOut && price !== null && (
                                <span className={classes.price}>{price}</span>
                            )}
                        </li>
                    );
                })}
            </ul>

            {rows.length > 1 && !bands.every(band => bandStatus(band).soldOut) && (
                <div className={classes.more}>
                    <UnstyledButton className={classes.toggle} aria-expanded={showAll} onClick={() => setShowAll(!showAll)}
                                    data-testid="seat-prices-toggle">
                        {showAll ? t`Hide all prices` : t`See all prices`}
                        <IconChevronDown size={16} className={showAll ? classes.chevronOpen : undefined}/>
                    </UnstyledButton>
                    {showAll && (
                        <div className={classes.allPrices} data-testid="seat-prices-all">
                            {bands.map(band => {
                                const status = bandStatus(band);
                                return (
                                    <div key={band.key} className={classes.bandGroup}>
                                        <div className={classes.keyLine}>
                                            {bandName(band)}
                                            <span className={classes.availability}>{status.text}</span>
                                        </div>
                                        {!status.soldOut && (
                                            <ul className={classes.bandPrices}>
                                                {rows.map(row => {
                                                    const price = priceValue(band, row);
                                                    return price !== null && (
                                                        <li key={row.key}>
                                                            <span>{row.label}</span>
                                                            <span className={classes.price}>{formatPrice(price, currency)}</span>
                                                        </li>
                                                    );
                                                })}
                                            </ul>
                                        )}
                                    </div>
                                );
                            })}
                            {rows.filter(row => row.unavailable !== null).map(row => (
                                <div key={row.key} className={classes.keyLine} data-testid={`seat-price-unavailable-${row.key}`}>
                                    <span className={classes.ticketName}>{row.label}</span>
                                    <span className={classes.availability}>{row.unavailable}</span>
                                </div>
                            ))}
                            {rows.some(row => row.description) && (
                                <div className={classes.descriptions}>
                                    <div className={classes.subheading}>{t`About the tickets`}</div>
                                    {rows.filter(row => row.description).map(row => (
                                        <div key={row.key} data-testid={`seat-price-details-${row.key}`}>
                                            <div className={classes.ticketName}>{row.label}</div>
                                            <UserGeneratedContent className={classes.description} html={row.description!}/>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
