import {Outlet} from "react-router";
import {Header} from "../../common/Header";
import {Container} from "@mantine/core";
import {GlobalMenu} from "../../common/GlobalMenu";
import ImpersonationBanner from "../../common/ImpersonationBanner";
import PendingDeletionBanner from "../../common/PendingDeletionBanner";

const DefaultLayout = () => {
    return (
        <>
            <ImpersonationBanner />
            <PendingDeletionBanner />
            <Header rightContent={<GlobalMenu/>}/>
            <Container>
                <Outlet/>
            </Container>
        </>
    );
}

export default DefaultLayout;
