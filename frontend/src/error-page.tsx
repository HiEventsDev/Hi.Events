import {Container} from '@mantine/core';
import {useRouteError} from "react-router-dom";

function ErrorPage() {
    const error = useRouteError();

    console.error(error);

    return (
        <div>
            <Container>
                Error
            </Container>
        </div>
    );
}

export default ErrorPage;

