import {Modal, Progress} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import Truncate from "../Truncate";
import {CheckInList} from "../../../types.ts";
import {PoweredByFooter} from "../PoweredByFooter";
import {getConfig} from "../../../utilites/config";

interface CheckInInfoModalProps {
    isOpen: boolean;
    checkInList: CheckInList | undefined;
    onClose: () => void;
}

export const CheckInInfoModal = ({
                                     isOpen,
                                     checkInList,
                                     onClose,
                                 }: CheckInInfoModalProps) => {
    if (!checkInList) return null;

    const total = checkInList.total_attendees;
    const checkedIn = checkInList.checked_in_attendees;
    const percent = total > 0 ? (checkedIn / total) * 100 : 0;
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");
    const logoSrc = getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-light.svg");

    return (
        <Modal
            opened={isOpen}
            onClose={onClose}
            title={<Truncate text={checkInList.name} length={30}/>}
            size="md"
            centered
            radius="md"
            padding={24}
            transitionProps={{transition: "fade", duration: 200}}
        >
            <div>
                <div style={{
                    fontSize: 13,
                    fontWeight: 700,
                    textTransform: "uppercase",
                    letterSpacing: "0.06em",
                    color: "var(--hi-color-gray-dark)",
                    marginBottom: 6,
                }}>
                    {t`Progress`}
                </div>
                <div style={{
                    fontSize: 22,
                    fontWeight: 800,
                    letterSpacing: "-0.02em",
                    marginBottom: 10,
                }}>
                    <Trans>{`${checkedIn} / ${total}`} checked in</Trans>
                </div>
                <Progress
                    value={percent}
                    color="teal"
                    size="lg"
                    radius="xl"
                />
            </div>

            {checkInList.description && (
                <div style={{
                    marginTop: 20,
                    padding: "14px 16px",
                    background: "var(--hi-color-gray)",
                    borderRadius: 10,
                    fontSize: 14,
                    lineHeight: 1.55,
                    whiteSpace: "pre-wrap",
                    color: "var(--hi-text)",
                }}>
                    <div style={{
                        fontSize: 11,
                        fontWeight: 700,
                        textTransform: "uppercase",
                        letterSpacing: "0.06em",
                        color: "var(--hi-color-gray-dark)",
                        marginBottom: 6,
                    }}>
                        {t`Staff instructions`}
                    </div>
                    {checkInList.description}
                </div>
            )}

            <div style={{
                marginTop: 24,
                paddingTop: 16,
                borderTop: "1px solid var(--hi-color-gray-2)",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: 8,
            }}>
                <img
                    src={logoSrc}
                    alt={t`${appName} logo`}
                    style={{height: 24, maxWidth: 160, objectFit: "contain", opacity: 0.85}}
                />
                <PoweredByFooter/>
            </div>
        </Modal>
    );
};
