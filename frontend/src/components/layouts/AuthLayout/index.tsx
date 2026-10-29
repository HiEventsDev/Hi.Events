import {Navigate, Outlet, useLocation} from "react-router";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useEffect} from "react";
import {captureUtmData} from "../../../utilites/utm.ts";
import {TWO_FACTOR_SETUP_PATH} from "../../../api/client.ts";
import {AuthShell} from "./AuthShell.tsx";

const SIGNED_IN_PATHS = [TWO_FACTOR_SETUP_PATH, '/auth/accept-invitation'];

const AuthLayout = () => {
    const me = useGetMe();
    const location = useLocation();

    useEffect(() => {
        captureUtmData();
    }, []);

    const allowsSignedInUsers = SIGNED_IN_PATHS.some(path => location.pathname.startsWith(path));

    if (me.isSuccess && !allowsSignedInUsers) {
        return <Navigate to={me.data?.two_factor_setup_required ? TWO_FACTOR_SETUP_PATH : '/manage/events'} />
    }

    return (
        <AuthShell>
            <Outlet />
        </AuthShell>
    );
};

export default AuthLayout;
