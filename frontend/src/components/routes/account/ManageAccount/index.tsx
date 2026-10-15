import {Card} from "../../../common/Card";
import {Tabs} from "@mantine/core";
import classes from "./ManageAccount.module.scss";
import {IconAdjustmentsCog, IconAlertTriangle, IconReceiptTax, IconUsers} from "@tabler/icons-react";
import {Navigate, Outlet, useLocation, useNavigate} from "react-router";
import {t} from "@lingui/macro";
import {useAccess} from "../../../../hooks/useAccess.ts";

export const ManageAccount = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const tabValue = location.pathname.split('/').pop() || 'settings';
    const {can, isRestricted} = useAccess();

    if (isRestricted) {
        return <Navigate to={'/manage/events'} replace/>;
    }

    return (
        <div className={classes.container}>
            <h1>{t`Account Settings`}</h1>
            <Card className={classes.tabsCard}>
                <Tabs value={tabValue} onChange={(value) => navigate(`/account/${value}`)}>
                    <Tabs.List grow>
                        <Tabs.Tab value="settings" leftSection={<IconAdjustmentsCog/>}>
                            {t`Account`}
                        </Tabs.Tab>
                        <Tabs.Tab value="taxes-and-fees" leftSection={<IconReceiptTax/>}>
                            {t`Tax & Fees`}
                        </Tabs.Tab>

                        {can('MANAGE_TEAM') && (
                            <Tabs.Tab value="users" leftSection={<IconUsers/>}>
                                {t`Users`}
                            </Tabs.Tab>
                        )}

                        {can('MANAGE_ACCOUNT') && (
                            <Tabs.Tab value="danger-zone" leftSection={<IconAlertTriangle/>} data-testid="danger-zone-tab">
                                {t`Danger Zone`}
                            </Tabs.Tab>
                        )}
                    </Tabs.List>
                </Tabs>
            </Card>
            <div className={classes.tabWrapper}>
                <Outlet/>
            </div>
        </div>
    );
};

export default ManageAccount;
