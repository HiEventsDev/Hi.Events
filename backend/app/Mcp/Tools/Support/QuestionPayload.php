<?php

namespace HiEvents\Mcp\Tools\Support;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class QuestionPayload
{
    public const FIELDS = [
        'title',
        'description',
        'type',
        'belongs_to',
        'options',
        'required',
        'is_hidden',
        'product_ids',
        'applies_to_all_tickets',
        'condition_question_id',
        'condition_values',
        'disqualifying_options',
        'disqualification_message',
    ];

    private const LINKED_FIELDS = [
        ['condition_question_id', 'condition_values'],
        ['disqualifying_options', 'disqualification_message'],
    ];

    public static function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('The question, e.g. "Any dietary requirements?".'),
            'description' => $schema->string()->description('Help text shown under the question, as HTML.'),
            'type' => $schema->string()->enum(QuestionTypeEnum::valuesArray())->description('Answer format. CHECKBOX, RADIO, DROPDOWN and MULTI_SELECT_DROPDOWN need options.'),
            'belongs_to' => $schema->string()->enum(QuestionBelongsTo::valuesArray())->description('ORDER asks once per order; PRODUCT asks for each ticket bought.'),
            'options' => $schema->array()->items($schema->string())->description('Answer choices for choice types, in display order.'),
            'required' => $schema->boolean()->description('Whether an answer is required.'),
            'is_hidden' => $schema->boolean()->description('Hide the question from buyers.'),
            'product_ids' => $schema->array()->items($schema->integer())->description('PRODUCT questions: the tickets that ask it.'),
            'applies_to_all_tickets' => $schema->boolean()->description('PRODUCT questions: ask for every ticket, including tickets added later.'),
            'condition_question_id' => $schema->integer()->nullable()->description('Makes this a follow-up: only asked when that choice question is answered with one of condition_values. Null removes the condition.'),
            'condition_values' => $schema->array()->items($schema->string())->description('Options of the parent question that show this follow-up.'),
            'disqualifying_options' => $schema->array()->items($schema->string())->nullable()->description('Options that stop the buyer from registering, e.g. "Under 18". Makes the question required. Null removes them.'),
            'disqualification_message' => $schema->string()->description('Message shown when a disqualifying option is chosen. Required with disqualifying_options.'),
        ];
    }

    public static function fromExisting(array $question): array
    {
        return [
            'title' => $question['title'],
            'description' => $question['description'] ?? null,
            'type' => $question['type'],
            'belongs_to' => $question['belongs_to'],
            'options' => array_values($question['options'] ?? []),
            'required' => (bool) ($question['required'] ?? false),
            'is_hidden' => (bool) ($question['is_hidden'] ?? false),
            'product_ids' => $question['product_ids'] ?? [],
            'applies_to_all_tickets' => (bool) ($question['applies_to_all_tickets'] ?? false),
        ];
    }

    public static function completeLinkedFields(array $existing, array $changes): array
    {
        foreach (self::LINKED_FIELDS as $fields) {
            if (array_intersect($fields, array_keys($changes)) === []) {
                continue;
            }

            foreach ($fields as $field) {
                if (! array_key_exists($field, $changes)) {
                    $changes[$field] = $existing[$field] ?? null;
                }
            }
        }

        return $changes;
    }

    public static function present(array $question): array
    {
        return [
            'id' => $question['id'],
            'title' => $question['title'],
            'description' => $question['description'] ?? null,
            'type' => $question['type'],
            'belongs_to' => $question['belongs_to'],
            'options' => array_values($question['options'] ?? []),
            'required' => (bool) ($question['required'] ?? false),
            'is_hidden' => (bool) ($question['is_hidden'] ?? false),
            'applies_to_all_tickets' => (bool) ($question['applies_to_all_tickets'] ?? false),
            'product_ids' => $question['product_ids'] ?? [],
            'condition_question_id' => $question['condition_question_id'] ?? null,
            'condition_values' => $question['condition_values'] ?? [],
            'disqualifying_options' => $question['disqualifying_options'] ?? [],
            'disqualification_message' => $question['disqualification_message'] ?? null,
        ];
    }
}
