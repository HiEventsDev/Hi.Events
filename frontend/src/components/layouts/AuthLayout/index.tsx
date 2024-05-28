import {Navigate, Outlet} from "react-router-dom";
import classes from "./Auth.module.scss";
import {t} from "@lingui/macro";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../common/PoweredByFooter";

const AuthLayout = () => {
    const me = useGetMe();
    if (me.isSuccess) {
        return <Navigate to={'/manage/events'}/>
    }

    return (
        <main className={classes.container}>
            <div className={classes.logo}>
                <img src={'/logo-dark.svg'} alt={t`hi.events logo`}/>
            </div>
            <div className={classes.wrapper}>
                <Outlet/>
                {/** PLEASE NOTE:*/}
                {/** Under the terms of the license, you are not permitted to remove or obscure the powered by footer unless you have a white-label*/}
                {/** or commercial license.*/}
                {/** @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13*/}
                {/** You can purchase a license at https://hi.events/licensing*/}
                <PoweredByFooter/>

            </div>
        </main>
    );
};

export default AuthLayout;