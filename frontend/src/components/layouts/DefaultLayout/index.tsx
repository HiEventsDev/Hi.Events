import {Outlet} from "react-router-dom";
import {Header} from "../../common/Header";
import {Container} from "@mantine/core";

const DefaultLayout = () => {
    return (
        <>
            <Header/>
            <Container>
                <Outlet/>
            </Container>
        </>
    );
}

export default DefaultLayout;