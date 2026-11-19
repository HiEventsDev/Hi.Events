<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmContactNoteDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactNoteRepositoryInterface;
use HiEvents\Models\CrmContactNote;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CrmContactNoteDomainObject>
 */
class CrmContactNoteRepository extends BaseRepository implements CrmContactNoteRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmContactNote::class;
    }

    public function getDomainObject(): string
    {
        return CrmContactNoteDomainObject::class;
    }

    public function findForContact(int $contactId): Collection
    {
        return $this->runQuery(function () use ($contactId) {
            $notes = $this->handleResults($this->model->where('contact_id', $contactId)->orderByDesc('id')->limit(200)->get());

            $authors = $this->db->table('users')
                ->whereIn('id', $notes->map(static fn (CrmContactNoteDomainObject $note) => $note->getUserId())->filter()->unique()->all())
                ->selectRaw("id, trim(concat_ws(' ', first_name, last_name)) AS name")
                ->pluck('name', 'id');

            return $notes->each(static fn (CrmContactNoteDomainObject $note) => $note->setAuthorName($authors[$note->getUserId()] ?? null));
        });
    }
}
