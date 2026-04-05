import {useParams, useSearchParams} from 'react-router';
import {useGetAttendees} from '../../../../queries/useGetAttendees.ts';
import {Attendee, QueryFilters} from '../../../../types.ts';
import {useEffect} from 'react';
import QRCode from 'react-qr-code';
import {t} from '@lingui/macro';
import classes from './PrintAttendeeQrCodes.module.scss';

const PrintAttendeeQrCodes = () => {
    const {eventId} = useParams();
    const [searchParams] = useSearchParams();
    const pageNumber = Number(searchParams.get('page') || 1);
    const perPage = Number(searchParams.get('per_page') || 50);

    const queryFilters: QueryFilters = {
        pageNumber,
        perPage,
    };

    const {data} = useGetAttendees(eventId, queryFilters);
    const attendees = data?.data;

    useEffect(() => {
        if (attendees?.length) {
            setTimeout(() => window?.print(), 800);
        }
    }, [attendees]);

    if (!attendees) {
        return <div className={classes.loading}>{t`Loading attendees...`}</div>;
    }

    return (
        <div className={classes.container}>
            <h1 className={classes.title}>{t`Attendee QR Codes`}</h1>
            <div className={classes.grid}>
                {attendees.map((attendee: Attendee) => (
                    <div key={attendee.id} className={classes.card}>
                        <div className={classes.qrWrapper}>
                            <QRCode
                                value={String(attendee.public_id)}
                                size={120}
                                level="M"
                                style={{height: 'auto', maxWidth: '100%', width: '100%'}}
                            />
                        </div>
                        <div className={classes.info}>
                            <div className={classes.name}>
                                {attendee.first_name} {attendee.last_name}
                            </div>
                            <div className={classes.detail}>{attendee.short_id}</div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default PrintAttendeeQrCodes;
