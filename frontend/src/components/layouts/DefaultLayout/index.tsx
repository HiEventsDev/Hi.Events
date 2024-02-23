import {Outlet} from "react-router-dom";
import {Header} from "../../common/Header";
import {Container} from "@mantine/core";

export const DefaultLayout = () => {
    return (
        <>
            <Header/>
            <Container>
                <Outlet/>
            </Container>
        </>
    );
}