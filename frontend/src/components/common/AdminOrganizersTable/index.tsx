import {Badge, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {AdminOrganizer} from "../../../api/admin.client";
import {IconCalendar, IconCoin, IconWorld} from "@tabler/icons-react";
import classes from "./AdminOrganizersTable.module.scss";

interface AdminOrganizersTableProps {
    organizers: AdminOrganizer[];
}

const AdminOrganizersTable = ({organizers}: AdminOrganizersTableProps) => {
    const navigate = useNavigate();

    if (!organizers || organizers.length === 0) {
        return (
            <div className={classes.emptyState}>
                <Text size="lg" c="dimmed">{t`No organizers found`}</Text>
            </div>
        );
    }

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'LIVE':
                return 'green';
            case 'DRAFT':
                return 'gray';
            case 'ARCHIVED':
                return 'red';
            default:
                return 'gray';
        }
    };

    const formatDate = (dateString?: string) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString();
    };

    return (
        <div className={classes.cardsContainer}>
            {organizers.map((organizer) => (
                <button
                    key={organizer.id}
                    type="button"
                    className={classes.organizerCard}
                    onClick={() => navigate(`/manage/organizer/${organizer.id}`)}
                    title={t`Open organizer dashboard`}
                >
                    <div className={classes.cardHeader}>
                        <div className={classes.organizerInfo}>
                            <h3 className={classes.organizerName}>{organizer.name}</h3>
                            <span className={classes.organizerEmail}>{organizer.email}</span>
                        </div>
                        <Badge size="sm" color={getStatusColor(organizer.status)}>
                            {organizer.status}
                        </Badge>
                    </div>

                    <div className={classes.cardBody}>
                        <div className={classes.section}>
                            <span className={classes.sectionLabel}>{t`Account`}</span>
                            {organizer.account ? (
                                <div className={classes.accountItem}>
                                    <span className={classes.accountName}>{organizer.account.name}</span>
                                </div>
                            ) : (
                                <Text size="sm" c="dimmed">{t`No account`}</Text>
                            )}
                        </div>

                        <div className={classes.cardFooter}>
                            <div className={classes.footerInfo}>
                                <div className={classes.footerItem}>
                                    <IconCalendar size={14}/>
                                    <span>{formatDate(organizer.created_at)}</span>
                                </div>
                                {organizer.timezone && (
                                    <div className={classes.footerItem}>
                                        <IconWorld size={14}/>
                                        <span>{organizer.timezone}</span>
                                    </div>
                                )}
                                {organizer.currency && (
                                    <div className={classes.footerItem}>
                                        <IconCoin size={14}/>
                                        <span>{organizer.currency}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </button>
            ))}
        </div>
    );
};

export default AdminOrganizersTable;
