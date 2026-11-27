import {useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Textarea, Tooltip} from "@mantine/core";
import {IconTrash} from "@tabler/icons-react";
import {SideDrawerSection} from "../../../../components/common/SideDrawer";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {relativeDate} from "../../../../utilites/dates.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {useCreateCrmContactNote} from "../../mutations/useCreateCrmContactNote.ts";
import {useDeleteCrmContactNote} from "../../mutations/useDeleteCrmContactNote.ts";
import {useGetCrmContactNotes} from "../../queries/useGetCrmContactNotes.ts";
import {CrmContactNote} from "../../types.ts";
import classes from "./ContactDrawer.module.scss";

export const Notes = ({organizerId, contactId, canAdd, canDelete}: {
    organizerId: IdParam,
    contactId: IdParam,
    canAdd: boolean,
    canDelete: boolean,
}) => {
    const notesQuery = useGetCrmContactNotes(organizerId, contactId);
    const createNote = useCreateCrmContactNote();
    const deleteNote = useDeleteCrmContactNote();
    const [draft, setDraft] = useState('');
    const notes = notesQuery.data ?? [];

    const handleAdd = () => createNote.mutate({organizerId, contactId, note: draft.trim()}, {
        onSuccess: () => setDraft(''),
        onError: error => showError(firstApiError(error, t`We couldn't save that note. Please try again.`)),
    });

    const handleDelete = (note: CrmContactNote) => confirmationDialog(
        t`Delete this note?`,
        () => deleteNote.mutate({organizerId, contactId, noteId: note.id}, {
            onError: error => showError(firstApiError(error, t`We couldn't delete that note. Please try again.`)),
        }),
        {confirm: t`Delete note`, destructive: true, description: t`This can't be undone.`},
    );

    return (
        <SideDrawerSection title={t`Notes`} count={notes.length || undefined}>
            {canAdd && (
                <div className={classes.noteForm}>
                    <Textarea
                        aria-label={t`Add a note`}
                        placeholder={t`Add a note for your team`}
                        value={draft}
                        onChange={event => setDraft(event.currentTarget.value)}
                        autosize
                        minRows={2}
                        maxRows={6}
                        maxLength={5000}
                    />
                    {draft.trim() !== '' && (
                        <div className={classes.noteActions}>
                            <Button size="xs" onClick={handleAdd} loading={createNote.isPending}>
                                {t`Save note`}
                            </Button>
                        </div>
                    )}
                </div>
            )}
            {notesQuery.isError && (
                <p className={classes.empty}>{t`We couldn't load this contact's notes. Please refresh the page.`}</p>
            )}
            {notesQuery.data && notes.length === 0 && !canAdd && <p className={classes.empty}>{t`No notes yet.`}</p>}
            {notes.map(note => (
                <div key={note.id} className={classes.note}>
                    <p>{note.note}</p>
                    <div className={classes.noteMeta}>
                        <span>{[note.author_name, relativeDate(note.created_at)].filter(Boolean).join(' · ')}</span>
                        {canDelete && (
                            <Tooltip label={t`Delete note`}>
                                <ActionIcon
                                    className={classes.touchTarget}
                                    variant="subtle"
                                    color="gray"
                                    size="md"
                                    aria-label={t`Delete note`}
                                    onClick={() => handleDelete(note)}
                                >
                                    <IconTrash size={14}/>
                                </ActionIcon>
                            </Tooltip>
                        )}
                    </div>
                </div>
            ))}
        </SideDrawerSection>
    );
};
