import React from "react";
import {Container} from "@mantine/core";

interface PageBodyProps {
    children: React.ReactNode,
    isFluid?: boolean,
}

export const PageBody = ({children, isFluid = true}: PageBodyProps) => {
    return (
        <Container style={{position:'relative'}} fluid={isFluid} p={0}>
            {children}
        </Container>
    )
}