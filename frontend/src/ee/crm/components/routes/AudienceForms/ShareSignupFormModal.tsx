import {t} from "@lingui/macro";
import {Anchor, Button, CopyButton, TextInput} from "@mantine/core";
import {IconCheck, IconCopy, IconDownload, IconExternalLink} from "@tabler/icons-react";
import {useRef} from "react";
import QRCode from "react-qr-code";
import {Modal} from "../../../../../components/common/Modal";
import {CrmSignupForm} from "../../../types.ts";
import {signupFormEmbedCode, signupFormUrl} from "../../../utilites/signupForms.ts";
import classes from "./Forms.module.scss";

const Copy = ({value}: {value: string}) => (
    <CopyButton value={value} timeout={1500}>
        {({copied, copy}) => (
            <Button
                variant="default"
                leftSection={copied ? <IconCheck size={14}/> : <IconCopy size={14}/>}
                onClick={copy}
            >
                {copied ? t`Copied` : t`Copy`}
            </Button>
        )}
    </CopyButton>
);

const QR_EXPORT_SIZE = 1024;

const downloadQrCode = (container: HTMLDivElement | null, fileName: string) => {
    const svg = container?.querySelector('svg');
    if (!svg) {
        return;
    }

    const image = new Image();
    image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = QR_EXPORT_SIZE;
        canvas.height = QR_EXPORT_SIZE;
        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, QR_EXPORT_SIZE, QR_EXPORT_SIZE);
        context.drawImage(image, 64, 64, QR_EXPORT_SIZE - 128, QR_EXPORT_SIZE - 128);
        const link = document.createElement('a');
        link.download = fileName;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };
    image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(new XMLSerializer().serializeToString(svg))}`;
};

export const ShareSignupFormModal = ({form, onClose}: {form: CrmSignupForm, onClose: () => void}) => {
    const url = signupFormUrl(form.public_id);
    const embedCode = signupFormEmbedCode(form.public_id);
    const qrRef = useRef<HTMLDivElement>(null);
    const name = form.name;

    return (
        <Modal opened onClose={onClose} heading={t`Share "${name}"`} size="lg">
            <section className={classes.shareSection}>
                <h3>{t`Link`}</h3>
                <p>{t`A page with just this form. Put it in your bio, a post or an email signature.`}</p>
                <div className={classes.linkRow}>
                    <TextInput value={url} readOnly aria-label={t`Form link`} onFocus={event => event.currentTarget.select()} data-testid="signup-form-link"/>
                    <Copy value={url}/>
                </div>
                <Anchor href={url} target="_blank" rel="noreferrer" size="xs">
                    <IconExternalLink size={12}/> {t`Open the page`}
                </Anchor>
            </section>

            <section className={classes.shareSection}>
                <h3>{t`QR code`}</h3>
                <div className={classes.qr} ref={qrRef}>
                    <QRCode value={url} size={112} aria-label={t`QR code for the form link`}/>
                    <div className={classes.qrText}>
                        <p>{t`For posters, flyers and the door at your events.`}</p>
                        <Button
                            variant="default"
                            size="xs"
                            leftSection={<IconDownload size={14}/>}
                            onClick={() => downloadQrCode(qrRef.current, `${form.public_id}-qr.png`)}
                        >
                            {t`Download PNG`}
                        </Button>
                    </div>
                </div>
            </section>

            <section className={classes.shareSection}>
                <h3>{t`Embed on your website`}</h3>
                <p>{t`Paste this where the form should appear. It fits the width of its container.`}</p>
                <pre className={classes.code}>{embedCode}</pre>
                <div>
                    <Copy value={embedCode}/>
                </div>
            </section>
        </Modal>
    );
};
