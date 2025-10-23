import {Modal, Progress} from "@mantine/core";
import {Trans} from "@lingui/macro";
import Truncate from "../Truncate";
import {CheckInList} from "../../../types.ts";
import classes from "../../layouts/CheckIn/CheckIn.module.scss";

interface CheckInInfoModalProps {
    isOpen: boolean;
    checkInList: CheckInList | undefined;
    onClose: () => void;
}

export const CheckInInfoModal = ({
    isOpen,
    checkInList,
    onClose
}: CheckInInfoModalProps) => {
    if (!checkInList) return null;
    
    return (
        <Modal.Root
            opened={isOpen}
            radius={0}
            onClose={onClose}
            transitionProps={{transition: 'fade', duration: 200}}
            padding={'none'}
        >
            <Modal.Overlay/>
            <Modal.Content>
                <Modal.Header>
                    <Modal.Title>
                        <Truncate text={checkInList.name} length={30}/>
                    </Modal.Title>
                    <Modal.CloseButton/>
                </Modal.Header>
                <div className={classes.infoModal}>
                    <div className={classes.checkInCount}>
                        <>
                            <h4>
                                <Trans>
                                    {`${checkInList.checked_in_attendees}/${checkInList.total_attendees}`} checked in
                                </Trans>
                            </h4>

                            <Progress
                                value={checkInList.checked_in_attendees / checkInList.total_attendees * 100}
                                color={'teal'}
                                size={'xl'}
                                className={classes.progressBar}
                            />
                        </>
                    </div>

                    {checkInList.description && (
                        <div className={classes.description}>
                            {checkInList.description}
                        </div>
                    )}
                </div>
            </Modal.Content>
        </Modal.Root>
    );
};
