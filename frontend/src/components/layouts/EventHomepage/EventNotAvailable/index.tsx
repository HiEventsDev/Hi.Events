import {t} from '@lingui/macro';
import {Center} from "../../../common/Center";

export const EventNotAvailable = () => {
    return (
        <Center>
            <p>
                {t`This event is not available at the moment. Please check back later.`}
            </p>
        </Center>
    )
}