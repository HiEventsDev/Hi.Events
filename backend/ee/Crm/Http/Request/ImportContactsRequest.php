<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Request;

use HiEvents\DomainObjects\Enums\CrmImportKind;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class ImportContactsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
            'kind' => ['nullable', 'string', Rule::in(CrmImportKind::valuesArray())],
            'consent_attested' => [Rule::prohibitedIf($this->isSuppressionImport()), 'nullable', 'boolean'],
            'tag_name' => [Rule::prohibitedIf($this->isSuppressionImport()), 'nullable', 'string', 'max:50'],
        ];
    }

    public function isSuppressionImport(): bool
    {
        return $this->input('kind') === CrmImportKind::SUPPRESSIONS->value;
    }
}
