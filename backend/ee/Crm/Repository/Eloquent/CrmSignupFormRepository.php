<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Eloquent;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Models\CrmSignupForm;
use HiEvents\Repository\Eloquent\BaseRepository;

/**
 * @extends BaseRepository<CrmSignupFormDomainObject>
 */
class CrmSignupFormRepository extends BaseRepository implements CrmSignupFormRepositoryInterface
{
    protected function getModel(): string
    {
        return CrmSignupForm::class;
    }

    public function getDomainObject(): string
    {
        return CrmSignupFormDomainObject::class;
    }

    public function hideOthersFromOrganizerPage(int $organizerId, int $exceptFormId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_signup_forms')
            ->where('organizer_id', $organizerId)
            ->where('id', '!=', $exceptFormId)
            ->where('show_on_organizer_page', true)
            ->update(['show_on_organizer_page' => false, 'updated_at' => now()]));
    }

    public function recordSubmission(int $formId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_signup_forms')->where('id', $formId)->increment('submissions_count'));
    }

    public function recordConfirmation(int $formId): void
    {
        $this->runQuery(fn () => $this->db->table('crm_signup_forms')->where('id', $formId)->incrementEach(
            ['confirmations_count' => 1],
            ['last_confirmed_at' => now()],
        ));
    }
}
