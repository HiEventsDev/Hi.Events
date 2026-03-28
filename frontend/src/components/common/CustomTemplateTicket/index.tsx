import {useRef, useState} from 'react';
import QRCode from 'react-qr-code';
import {Attendee, Event} from '../../../types.ts';
import {imageUrl} from '../../../utilites/urlHelper.ts';

interface CustomTemplateTicketProps {
    event: Event;
    attendee: Attendee;
}

export const CustomTemplateTicket = ({event, attendee}: CustomTemplateTicketProps) => {
    const imgRef = useRef<HTMLImageElement>(null);
    const [naturalSize, setNaturalSize] = useState<{w: number; h: number} | null>(null);

    const templateUrl = imageUrl('TICKET_TEMPLATE', event.images);
    const settings = event.settings?.ticket_design_settings;
    const qrX = settings?.qr_x ?? 0;
    const qrY = settings?.qr_y ?? 0;
    const qrSize = settings?.qr_size ?? 120;
    const numX = settings?.num_x ?? null;
    const numY = settings?.num_y ?? null;

    // short_id is a non-optional string on the Attendee type
    const counterText = String(attendee.short_id ?? attendee.public_id ?? '').padStart(3, '0');

    if (!templateUrl) {
        return null;
    }

    return (
        <div style={{pageBreakAfter: 'always'}}>
            <div
                style={{
                    position: 'relative',
                    width: naturalSize ? naturalSize.w : 'auto',
                    height: naturalSize ? naturalSize.h : 'auto',
                    display: 'inline-block',
                }}
            >
                <img
                    ref={imgRef}
                    src={templateUrl}
                    alt=""
                    onLoad={() => {
                        if (imgRef.current) {
                            setNaturalSize({w: imgRef.current.naturalWidth, h: imgRef.current.naturalHeight});
                        }
                    }}
                    style={{display: 'block', width: '100%', height: '100%'}}
                />

                {naturalSize && (
                    <>
                        <div
                            style={{
                                position: 'absolute',
                                left: qrX,
                                top: qrY,
                                width: qrSize,
                                height: qrSize,
                            }}
                        >
                            <QRCode
                                value={attendee.public_id ?? ''}
                                size={qrSize}
                                style={{width: '100%', height: '100%'}}
                            />
                        </div>

                        {numX !== null && numY !== null && (
                            <div
                                style={{
                                    position: 'absolute',
                                    left: numX,
                                    top: numY,
                                    fontSize: 24,
                                    fontWeight: 700,
                                    color: '#000000',
                                    lineHeight: 1,
                                }}
                            >
                                {counterText}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};
