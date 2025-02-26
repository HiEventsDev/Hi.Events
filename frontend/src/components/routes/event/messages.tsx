import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {SearchBarWrapper} from "../../common/SearchBar";
import {useDisclosure} from "@mantine/hooks";
import {Pagination} from "../../common/Pagination";
import {Button} from "@mantine/core";
import {IconSend} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {MessageType, QueryFilters} from "../../../types.ts";
import {TableSkeleton} from "../../common/TableSkeleton";
import {useGetEventMessages} from "../../../queries/useGetEventMessages.ts";
import {MessageList} from "../../common/MessageList";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {t} from "@lingui/macro";

export const Messages = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParam] = useFilterQueryParamSync();
    const messagesQuery = useGetEventMessages(eventId, searchParams as QueryFilters);
    const messages = messagesQuery?.data?.data;
    const pagination = messagesQuery?.data?.meta;
    const [sendModalOpen, {open: openSendModal, close: closeSendModal}] = useDisclosure(false);

    return (
        <>
            <PageBody isFluid={false}>
                <PageTitle>{t`Messages`}</PageTitle>
                <ToolBar searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by subject or content...`}
                        setSearchParams={setSearchParam}
                        searchParams={searchParams}
                        pagination={pagination}
                    />
                )}>
                    <Button color={'green'} size={'sm'} onClick={openSendModal} rightSection={<IconSend/>}>
                        {t`Send Message`}
                    </Button>
                </ToolBar>

                <TableSkeleton isVisible={!messages || !event}/>

                {(event && messages) && (
                    <MessageList messages={messages}/>
                )}

                <TableSkeleton isVisible={!messages || messagesQuery.isFetching}/>

                {!!messages?.length && (
                    <Pagination
                        value={searchParams.pageNumber}
                        onChange={(value) => setSearchParam({pageNumber: value})}
                        total={Number(pagination?.last_page)}
                    />
                )}
            </PageBody>

            {sendModalOpen && <SendMessageModal messageType={MessageType.OrderOwner} onClose={closeSendModal}/>}
        </>
    );
};

export default Messages;
