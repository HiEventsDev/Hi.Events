<?php

namespace Tests\Unit\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Application\Handlers\Question\EditQuestionHandler;
use HiEvents\Services\Domain\Question\EditQuestionService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class EditQuestionHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testDescriptionIsPurifiedOnEdit(): void
    {
        $editQuestionService = Mockery::mock(EditQuestionService::class);
        $purifier = Mockery::mock(HtmlPurifierService::class);

        $purifier->shouldReceive('purify')->andReturnUsing(fn($v) => is_string($v) ? 'PURIFIED:' . $v : $v);

        $capturedQuestion = null;
        $editQuestionService
            ->shouldReceive('editQuestion')
            ->once()
            ->andReturnUsing(function ($question) use (&$capturedQuestion) {
                $capturedQuestion = $question;
                return $question;
            });

        $handler = new EditQuestionHandler($editQuestionService, $purifier);

        $dto = new UpsertQuestionDTO(
            title: 'Dietary requirements',
            type: QuestionTypeEnum::SINGLE_LINE_TEXT,
            required: false,
            options: null,
            event_id: 1,
            product_ids: [],
            is_hidden: false,
            belongs_to: QuestionBelongsTo::ORDER,
            description: '<img src=x onerror=alert(1)>',
        );

        $handler->handle(7, $dto);

        $this->assertInstanceOf(QuestionDomainObject::class, $capturedQuestion);
        $this->assertSame('PURIFIED:<img src=x onerror=alert(1)>', $capturedQuestion->getDescription());
    }
}
