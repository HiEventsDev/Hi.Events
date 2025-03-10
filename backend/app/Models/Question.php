<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            QuestionDomainObjectAbstract::OPTIONS => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'product_questions')
            ->whereNull('product_questions.deleted_at');
    }
}
