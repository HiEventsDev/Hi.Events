import {useIsFetching} from "@tanstack/react-query";
import {LoadingOverlay} from "@mantine/core";

interface LoadingMaskProps {
    margin?: number | string
}

export const LoadingMask = ({margin = '5px'}: LoadingMaskProps) => {
    const isFetching = useIsFetching();

    return (
        <div style={{margin: margin}}>
            <LoadingOverlay loaderProps={{
                size: 30,
                type: 'dots',
            }} visible={isFetching > 0}/>
        </div>
    )
}
