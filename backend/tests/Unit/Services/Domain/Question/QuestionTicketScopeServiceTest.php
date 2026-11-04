<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Question\QuestionTicketScopeService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class QuestionTicketScopeServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ProductRepositoryInterface&MockInterface $productRepository;

    private QuestionRepositoryInterface&MockInterface $questionRepository;

    private QuestionTicketScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->questionRepository = Mockery::mock(QuestionRepositoryInterface::class);
        $this->service = new QuestionTicketScopeService($this->productRepository, $this->questionRepository);
    }

    public function test_the_flag_only_applies_to_attendee_questions(): void
    {
        $this->assertTrue($this->service->appliesToAllTickets($this->question(QuestionBelongsTo::PRODUCT, true)));
        $this->assertFalse($this->service->appliesToAllTickets($this->question(QuestionBelongsTo::ORDER, true)));
        $this->assertFalse($this->service->appliesToAllTickets($this->question(QuestionBelongsTo::PRODUCT, false)));
    }

    public function test_given_products_are_kept_when_the_question_is_for_specific_tickets(): void
    {
        $this->productRepository->shouldNotReceive('findWhere');

        $this->assertSame([4, 9], $this->service->resolveProductIds($this->question(QuestionBelongsTo::PRODUCT, false), [4, 9]));
    }

    public function test_every_ticket_in_the_event_is_used_when_the_question_is_for_all_tickets(): void
    {
        $this->productRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with(['event_id' => 12, 'product_type' => ProductType::TICKET->name])
            ->andReturn(collect([$this->product(3, ProductType::TICKET), $this->product(7, ProductType::TICKET)]));

        $this->assertSame([3, 7], $this->service->resolveProductIds($this->question(QuestionBelongsTo::PRODUCT, true), [99]));
    }

    public function test_a_ticket_is_attached_to_all_ticket_questions(): void
    {
        $this->expectAllTicketQuestions([21, 22]);
        $this->questionRepository->shouldReceive('attachProductToQuestions')->once()->with(5, [21, 22]);
        $this->questionRepository->shouldNotReceive('detachProductFromQuestions');

        $this->service->syncProduct($this->product(5, ProductType::TICKET));
    }

    public function test_a_non_ticket_product_is_detached_from_all_ticket_questions(): void
    {
        $this->expectAllTicketQuestions([21]);
        $this->questionRepository->shouldReceive('detachProductFromQuestions')->once()->with(5, [21]);
        $this->questionRepository->shouldNotReceive('attachProductToQuestions');

        $this->service->syncProduct($this->product(5, ProductType::GENERAL));
    }

    public function test_nothing_changes_when_no_question_is_for_all_tickets(): void
    {
        $this->expectAllTicketQuestions([]);
        $this->questionRepository->shouldNotReceive('attachProductToQuestions');
        $this->questionRepository->shouldNotReceive('detachProductFromQuestions');

        $this->service->syncProduct($this->product(5, ProductType::TICKET));
    }

    private function expectAllTicketQuestions(array $questionIds): void
    {
        $this->questionRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([
                'event_id' => 12,
                'belongs_to' => QuestionBelongsTo::PRODUCT->name,
                'applies_to_all_tickets' => true,
            ])
            ->andReturn(collect(array_map(
                fn (int $id) => (new QuestionDomainObject)->setId($id),
                $questionIds,
            )));
    }

    private function question(QuestionBelongsTo $belongsTo, bool $appliesToAllTickets): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setEventId(12)
            ->setBelongsTo($belongsTo->name)
            ->setAppliesToAllTickets($appliesToAllTickets);
    }

    private function product(int $id, ProductType $productType): ProductDomainObject
    {
        return (new ProductDomainObject)
            ->setId($id)
            ->setEventId(12)
            ->setProductType($productType->name);
    }
}
