import {SimpleGrid} from "@mantine/core";
import React from "react";

interface InputGroupProps {
    children: React.ReactNode;
}

export const InputGroup = ({children}: InputGroupProps) => {
    return (
        <SimpleGrid verticalSpacing={0} cols={{base: 1, xs: 2}}>
            {children}
        </SimpleGrid>
    )
}
