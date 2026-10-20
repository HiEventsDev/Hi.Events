<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $account_id
 * @property bool $smtp_enabled
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_encryption
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $mail_from_address
 * @property string|null $mail_from_name
 */
class AccountEmailSetting extends BaseModel
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'account_email_settings';

    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'smtp_enabled',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'mail_from_address',
            'mail_from_name',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'smtp_enabled' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
