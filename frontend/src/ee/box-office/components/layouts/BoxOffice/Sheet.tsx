import {ReactNode} from "react";
import {Drawer} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";

interface SheetProps {
    opened: boolean;
    onClose: () => void;
    title: ReactNode;
    children: ReactNode;
}

export const DESKTOP_QUERY = '(min-width: 900px)';

export const Sheet = ({opened, onClose, title, children}: SheetProps) => {
    const isDesktop = useMediaQuery(DESKTOP_QUERY);

    return (
        <Drawer
            opened={opened}
            onClose={onClose}
            position={isDesktop ? 'right' : 'bottom'}
            size={isDesktop ? 420 : 'auto'}
            title={title}
            overlayProps={{backgroundOpacity: 0.45, blur: 2}}
            styles={{
                content: isDesktop ? {} : {borderRadius: '20px 20px 0 0', maxHeight: '92dvh'},
                title: {fontWeight: 700, fontSize: 17},
            }}
        >
            {children}
        </Drawer>
    );
};
