import {ActionIcon, Badge, Button, Group} from "@mantine/core";
import {IconDeviceMobile, IconPlus, IconRefresh, IconTrash} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {Card} from "../../../../../../../components/common/Card";
import {HeadingWithDescription} from "../../../../../../../components/common/Card/CardHeading";
import {Callout} from "../../../../../../../components/common/Callout";
import {useGetOrganizerTerminalReaders} from "../../../../../queries/useGetOrganizerTerminalReaders.ts";
import {useDeleteTerminalReader} from "../../../../../mutations/useDeleteTerminalReader.ts";
import {AddTerminalReaderModal} from "../../../../modals/AddTerminalReaderModal";
import {confirmationDialog} from "../../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../../utilites/notifications.tsx";
import {TerminalReader} from "../../../../../../../types.ts";
import classes from "./TerminalReaders.module.scss";
import {useLicensedFeature} from "../../../../../../licensing/hooks/useLicensedFeature.ts";
import {LicenceLockedCallout} from "../../../../../../licensing/components/LicenceLockedCallout";
import {FeatureFlag} from "../../../../../../../constants/featureFlags.ts";

const statusColor = (status: TerminalReader['status']) => {
    switch (status) {
        case 'online':
            return 'green';
        case 'offline':
            return 'gray';
        case 'unavailable':
            return 'red';
        default:
            return 'yellow';
    }
};

const statusLabel = (status: TerminalReader['status']) => {
    switch (status) {
        case 'online':
            return t`Online`;
        case 'offline':
            return t`Offline`;
        case 'unavailable':
            return t`Unavailable, re-register`;
        default:
            return t`Status unknown`;
    }
};

export const TerminalReadersSettings = () => {
    const {organizerId} = useParams();
    const readersQuery = useGetOrganizerTerminalReaders(organizerId, !!organizerId);
    const deleteMutation = useDeleteTerminalReader();
    const [addOpen, {open: openAdd, close: closeAdd}] = useDisclosure(false);
    const data = readersQuery.data;
    const isLocked = useLicensedFeature(FeatureFlag.BOX_OFFICE).isSetupLocked;

    const handleDelete = (reader: TerminalReader) => {
        confirmationDialog(t`Remove this card reader? Staff using it will be asked to pick another reader or take cash. You can pair it again later.`, () => {
            deleteMutation.mutate({organizerId, readerId: reader.id}, {
                onSuccess: () => showSuccess(t`Card reader removed. The device shows as unregistered once it next connects to Stripe, or after a restart.`),
                onError: () => showError(t`Unable to remove the card reader`),
            });
        });
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Card readers`}
                description={t`Pair Stripe Terminal readers to take card payments at the box office.`}
            />

            {isLocked && <LicenceLockedCallout/>}

            {data && !data.stripe_configured && (
                <Callout variant="info">
                    <Trans>Stripe is not configured on this installation, so card readers are unavailable.</Trans>
                </Callout>
            )}

            {data && data.stripe_configured && !data.stripe_connected && (
                <Callout variant="info">
                    <Trans>Connect a Stripe account under Payouts before adding card readers.</Trans>
                </Callout>
            )}

            {data?.stripe_connected && (
                <>
                    <div className={classes.list}>
                        {data.readers.length === 0 && (
                            <div className={classes.empty}>{t`No card readers yet. Pair a Stripe Reader S700, S710 or WisePOS E to get started.`}</div>
                        )}
                        {data.readers.map(reader => (
                            <div key={reader.id} className={classes.row}>
                                <IconDeviceMobile size={20}/>
                                <div className={classes.rowMain}>
                                    <div className={classes.rowLabel}>{reader.label}</div>
                                    <div className={classes.rowMeta}>{reader.device_type ?? t`Stripe Terminal reader`}</div>
                                </div>
                                <Badge color={statusColor(reader.status)} variant="light">{statusLabel(reader.status)}</Badge>
                                <ActionIcon
                                    variant="subtle"
                                    color="red"
                                    aria-label={t`Remove card reader`}
                                    onClick={() => handleDelete(reader)}
                                    data-testid="terminal-reader-delete-button"
                                >
                                    <IconTrash size={16}/>
                                </ActionIcon>
                            </div>
                        ))}
                    </div>
                    <Group mt="md">
                        {!isLocked && (
                            <Button leftSection={<IconPlus size={16}/>} onClick={openAdd} data-testid="terminal-reader-add-button">
                                {t`Add reader`}
                            </Button>
                        )}
                        <ActionIcon variant="light" aria-label={t`Refresh reader status`} onClick={() => readersQuery.refetch()}>
                            <IconRefresh size={16}/>
                        </ActionIcon>
                    </Group>
                </>
            )}

            {addOpen && organizerId && <AddTerminalReaderModal onClose={closeAdd} organizerId={organizerId}/>}
        </Card>
    );
};
