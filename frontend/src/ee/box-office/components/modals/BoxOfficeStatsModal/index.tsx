import {ReactNode, useState} from "react";
import {Alert, Loader, Text, VisuallyHidden} from "@mantine/core";
import {DatePickerInput} from "@mantine/dates";
import {IconCalendar} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import '@mantine/dates/styles.css';
import {BoxOffice, BoxOfficeStatsRange, BoxOfficeStatsTotals, GenericModalProps, IdParam} from "../../../../../types.ts";
import {Modal} from "../../../../../components/common/Modal";
import {TanStackTable, TanStackTableColumn} from "../../../../../components/common/TanStackTable";
import {useGetBoxOfficeStats} from "../../../queries/useGetBoxOfficeStats.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {getBoxOfficeTenderLabel} from "../../../utilites/boxOfficeTender.ts";
import classes from "./BoxOfficeStatsModal.module.scss";

interface BoxOfficeStatsModalProps {
    eventId: IdParam;
    boxOffice: BoxOffice;
}

interface BreakdownRow extends BoxOfficeStatsTotals {
    key: string;
    label: ReactNode;
}

const Breakdown = ({title, rows, currency}: { title: string; rows: BreakdownRow[]; currency: string }) => (
    <div>
        <h4 className={classes.sectionTitle}>{title}</h4>
        {rows.length === 0 ? (
            <Text size="sm" c="dimmed">{t`No sales in this period`}</Text>
        ) : (
            <TanStackTable
                data={rows}
                columns={[
                    {id: 'label', header: () => <VisuallyHidden>{title}</VisuallyHidden>, meta: {sticky: 'left'}, cell: ({row: {original: row}}) => row.label},
                    {id: 'orders', header: t`Orders`, meta: {align: 'right'}, cell: ({row: {original: row}}) => row.orders},
                    {id: 'gross', header: t`Gross`, meta: {align: 'right'}, cell: ({row: {original: row}}) => formatCurrency(row.gross, currency)},
                    {id: 'refunded', header: t`Refunded`, meta: {align: 'right'}, cell: ({row: {original: row}}) => formatCurrency(row.refunded, currency)},
                ] satisfies TanStackTableColumn<BreakdownRow>[]}
            />
        )}
    </div>
);

export const BoxOfficeStatsModal = ({onClose, eventId, boxOffice}: GenericModalProps & BoxOfficeStatsModalProps) => {
    const [dates, setDates] = useState<[string | null, string | null]>([null, null]);
    const [range, setRange] = useState<BoxOfficeStatsRange>({from: null, to: null});
    const statsQuery = useGetBoxOfficeStats(eventId, boxOffice.id as IdParam, range);
    const stats = statsQuery.data;

    const changeDates = (value: [string | null, string | null]) => {
        setDates(value);
        const [from, to] = value;
        if ((from && to) || (!from && !to)) {
            setRange({from, to});
        }
    };

    return (
        <Modal opened onClose={onClose} heading={t`Sales summary for ${boxOffice.name}`}>
            <div className={classes.content} data-testid="box-office-stats-modal">
                <DatePickerInput
                    type="range"
                    clearable
                    label={t`Date range`}
                    placeholder={t`All time`}
                    leftSection={<IconCalendar stroke={1.5} size={18}/>}
                    value={dates}
                    onChange={changeDates}
                    maxDate={new Date()}
                    className={classes.datePicker}
                />

                {statsQuery.isError && (
                    <Alert color="red" variant="light">{t`The sales summary could not be loaded`}</Alert>
                )}

                {!stats && statsQuery.isLoading && <Loader size="sm"/>}

                {stats && (
                    <>
                        <div className={classes.totals} data-testid="box-office-stats-totals">
                            <div className={classes.total}>
                                <span className={classes.totalLabel}>{t`Orders`}</span>
                                <span className={classes.totalValue} data-testid="box-office-stats-orders">{stats.orders}</span>
                            </div>
                            <div className={classes.total}>
                                <span className={classes.totalLabel}>{t`Gross`}</span>
                                <span className={classes.totalValue} data-testid="box-office-stats-gross">
                                    {formatCurrency(stats.gross, stats.currency)}
                                </span>
                            </div>
                            <div className={classes.total}>
                                <span className={classes.totalLabel}>{t`Refunded`}</span>
                                <span className={classes.totalValue} data-testid="box-office-stats-refunded">
                                    {formatCurrency(stats.refunded, stats.currency)}
                                </span>
                            </div>
                        </div>

                        <Breakdown
                            title={t`By payment type`}
                            currency={stats.currency}
                            rows={stats.by_tender.map(row => ({
                                ...row,
                                key: row.tender,
                                label: getBoxOfficeTenderLabel(row.tender) ?? row.tender,
                            }))}
                        />
                        <Breakdown
                            title={t`By operator`}
                            currency={stats.currency}
                            rows={stats.by_operator.map(row => ({...row, key: row.operator_name, label: row.operator_name}))}
                        />
                        <Breakdown
                            title={t`By day`}
                            currency={stats.currency}
                            rows={stats.by_day.map(row => ({
                                ...row,
                                key: row.day,
                                label: formatDateWithLocale(row.day, 'shortDate', 'UTC'),
                            }))}
                        />
                    </>
                )}
            </div>
        </Modal>
    );
};
