<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Unit\Repository\Fixtures\WidgetCategoryDomainObject;
use Tests\Unit\Repository\Fixtures\WidgetCategoryModel;
use Tests\Unit\Repository\Fixtures\WidgetCategoryRepository;
use Tests\Unit\Repository\Fixtures\WidgetDomainObject;
use Tests\Unit\Repository\Fixtures\WidgetModel;
use Tests\Unit\Repository\Fixtures\WidgetRepository;

/**
 * Exercises HiEvents\Repository\Eloquent\BaseRepository against an isolated
 * fixture schema (br_test_widgets / br_test_widget_categories) so the test is
 * decoupled from production tables.
 *
 * Tables are created in setUp() and dropped in tearDown(). The DatabaseTransactions
 * trait wraps each test in a transaction, so any data inserted is rolled back too.
 */
class BaseRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private WidgetRepository $repository;

    private WidgetCategoryRepository $categoryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('br_test_widget_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('br_test_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->repository = $this->app->make(WidgetRepository::class);
        $this->categoryRepository = $this->app->make(WidgetCategoryRepository::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('br_test_widgets');
        Schema::dropIfExists('br_test_widget_categories');

        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeCategory(string $name = 'Default'): WidgetCategoryModel
    {
        $category = new WidgetCategoryModel;
        $category->name = $name;
        $category->save();

        return $category;
    }

    private function makeWidget(array $overrides = []): WidgetModel
    {
        $widget = new WidgetModel;
        $widget->fill(array_merge([
            'name' => 'Widget '.uniqid('', true),
            'sku' => 'SKU-'.uniqid('', true),
            'quantity' => 10,
            'price' => 9.99,
            'is_active' => true,
            'category_id' => null,
        ], $overrides));
        $widget->save();

        return $widget;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  create / insert
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_inserts_a_row_and_hydrates_a_domain_object(): void
    {
        $widget = $this->repository->create([
            'name' => 'Sprocket',
            'sku' => 'SP-001',
            'quantity' => 5,
            'price' => 12.50,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(WidgetDomainObject::class, $widget);
        $this->assertNotNull($widget->getId());
        $this->assertSame('Sprocket', $widget->getName());
        $this->assertSame(5, $widget->getQuantity());
        $this->assertSame(12.50, $widget->getPrice());
        $this->assertTrue($widget->getIsActive());

        $this->assertDatabaseHas('br_test_widgets', ['sku' => 'SP-001']);
    }

    public function test_insert_bulk_inserts_rows_and_autofills_timestamps(): void
    {
        $result = $this->repository->insert([
            ['name' => 'A', 'sku' => 'A-1', 'quantity' => 1, 'price' => 1, 'is_active' => true],
            ['name' => 'B', 'sku' => 'B-1', 'quantity' => 2, 'price' => 2, 'is_active' => true],
        ]);

        $this->assertTrue($result);
        $this->assertSame(2, WidgetModel::query()->count());
        // both rows should have timestamps populated by the base repository
        $this->assertSame(0, WidgetModel::query()->whereNull('created_at')->count());
        $this->assertSame(0, WidgetModel::query()->whereNull('updated_at')->count());
    }

    public function test_insert_preserves_caller_supplied_timestamps(): void
    {
        $supplied = '2020-01-01 00:00:00';

        $this->repository->insert([
            [
                'name' => 'A',
                'sku' => 'A-1',
                'quantity' => 1,
                'price' => 1,
                'is_active' => true,
                'created_at' => $supplied,
                'updated_at' => $supplied,
            ],
        ]);

        $this->assertSame(1, WidgetModel::query()->where('created_at', $supplied)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  findById / findFirst / findFirstByField / findFirstWhere
    // ─────────────────────────────────────────────────────────────────────────

    public function test_find_by_id_returns_hydrated_domain_object(): void
    {
        $widget = $this->makeWidget(['name' => 'Cog']);

        $found = $this->repository->findById($widget->id);

        $this->assertInstanceOf(WidgetDomainObject::class, $found);
        $this->assertSame($widget->id, $found->getId());
        $this->assertSame('Cog', $found->getName());
    }

    public function test_find_by_id_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->repository->findById(999_999);
    }

    public function test_find_first_returns_domain_object_when_present(): void
    {
        $widget = $this->makeWidget(['name' => 'Hinge']);

        $found = $this->repository->findFirst($widget->id);

        $this->assertNotNull($found);
        $this->assertSame('Hinge', $found->getName());
    }

    public function test_find_first_by_field_returns_match(): void
    {
        $this->makeWidget(['sku' => 'UNIQ-1']);

        $found = $this->repository->findFirstByField('sku', 'UNIQ-1');

        $this->assertNotNull($found);
        $this->assertSame('UNIQ-1', $found->getSku());
    }

    public function test_find_first_by_field_returns_null_when_no_match(): void
    {
        $found = $this->repository->findFirstByField('sku', 'does-not-exist');

        $this->assertNull($found);
    }

    public function test_find_first_where_returns_first_matching_row(): void
    {
        $this->makeWidget(['name' => 'A', 'is_active' => false]);
        $this->makeWidget(['name' => 'B', 'is_active' => true]);

        $found = $this->repository->findFirstWhere(['is_active' => true]);

        $this->assertNotNull($found);
        $this->assertSame('B', $found->getName());
    }

    public function test_find_first_where_returns_null_when_no_match(): void
    {
        $this->makeWidget(['is_active' => true]);

        $this->assertNull($this->repository->findFirstWhere(['is_active' => false]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  findWhere / findWhereIn / all / countWhere
    // ─────────────────────────────────────────────────────────────────────────

    public function test_find_where_returns_collection_of_domain_objects(): void
    {
        $this->makeWidget(['name' => 'A', 'is_active' => true]);
        $this->makeWidget(['name' => 'B', 'is_active' => true]);
        $this->makeWidget(['name' => 'C', 'is_active' => false]);

        $results = $this->repository->findWhere(['is_active' => true]);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(WidgetDomainObject::class, $results);
    }

    public function test_find_where_orders_results_using_order_and_directions(): void
    {
        $this->makeWidget(['name' => 'B']);
        $this->makeWidget(['name' => 'A']);
        $this->makeWidget(['name' => 'C']);

        $results = $this->repository->findWhere(
            where: [],
            orderAndDirections: [new OrderAndDirection('name', 'asc')],
        );

        $names = $results->map(fn (WidgetDomainObject $w) => $w->getName())->all();
        $this->assertSame(['A', 'B', 'C'], $names);
    }

    public function test_find_where_in_filters_by_inclusion_with_additional_where(): void
    {
        $w1 = $this->makeWidget(['name' => 'X', 'is_active' => true]);
        $w2 = $this->makeWidget(['name' => 'Y', 'is_active' => false]);
        $this->makeWidget(['name' => 'Z', 'is_active' => true]);

        $results = $this->repository->findWhereIn(
            field: 'id',
            values: [$w1->id, $w2->id],
            additionalWhere: ['is_active' => true],
        );

        $this->assertCount(1, $results);
        $this->assertSame('X', $results->first()->getName());
    }

    public function test_all_returns_every_row(): void
    {
        $this->makeWidget();
        $this->makeWidget();
        $this->makeWidget();

        $this->assertCount(3, $this->repository->all());
    }

    public function test_count_where_counts_matching_rows(): void
    {
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => false]);

        $this->assertSame(2, $this->repository->countWhere(['is_active' => true]));
        $this->assertSame(3, $this->repository->countWhere([]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  applyConditions DSL
    // ─────────────────────────────────────────────────────────────────────────

    public function test_apply_conditions_supports_in_operator(): void
    {
        $a = $this->makeWidget();
        $b = $this->makeWidget();
        $this->makeWidget();

        $results = $this->repository->findWhere([
            ['id', 'in', [$a->id, $b->id]],
        ]);

        $this->assertCount(2, $results);
    }

    public function test_apply_conditions_supports_not_in_operator(): void
    {
        $a = $this->makeWidget();
        $this->makeWidget();
        $this->makeWidget();

        $results = $this->repository->findWhere([
            ['id', 'not in', [$a->id]],
        ]);

        $this->assertCount(2, $results);
    }

    public function test_apply_conditions_supports_null_operator(): void
    {
        $this->makeWidget(['description' => null]);
        $this->makeWidget(['description' => 'has text']);

        $results = $this->repository->findWhere([
            ['description', 'null', null],
        ]);

        $this->assertCount(1, $results);
    }

    public function test_apply_conditions_supports_not_null_operator(): void
    {
        $this->makeWidget(['description' => null]);
        $this->makeWidget(['description' => 'has text']);

        $results = $this->repository->findWhere([
            ['description', 'not null', null],
        ]);

        $this->assertCount(1, $results);
    }

    public function test_apply_conditions_supports_comparison_operators(): void
    {
        $this->makeWidget(['quantity' => 5]);
        $this->makeWidget(['quantity' => 10]);
        $this->makeWidget(['quantity' => 15]);

        $this->assertCount(2, $this->repository->findWhere([['quantity', '>=', 10]]));
        $this->assertCount(1, $this->repository->findWhere([['quantity', '<', 10]]));
        $this->assertCount(1, $this->repository->findWhere([['quantity', '=', 15]]));
    }

    public function test_apply_conditions_treats_simple_pairs_as_equality(): void
    {
        $this->makeWidget(['name' => 'foo']);
        $this->makeWidget(['name' => 'bar']);

        $results = $this->repository->findWhere(['name' => 'foo']);

        $this->assertCount(1, $results);
    }

    public function test_apply_conditions_supports_callable_value(): void
    {
        $this->makeWidget(['name' => 'foo', 'is_active' => true]);
        $this->makeWidget(['name' => 'bar', 'is_active' => true]);
        $this->makeWidget(['name' => 'foo', 'is_active' => false]);

        $results = $this->repository->findWhere([
            'name' => 'foo',
            // closure-as-value path through applyConditions
            fn ($q) => $q->where('is_active', true),
        ]);

        $this->assertCount(1, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  update / delete
    // ─────────────────────────────────────────────────────────────────────────

    public function test_update_from_array_persists_changes_and_returns_fresh_object(): void
    {
        $widget = $this->makeWidget(['name' => 'old', 'quantity' => 1]);

        $updated = $this->repository->updateFromArray($widget->id, [
            'name' => 'new',
            'quantity' => 99,
        ]);

        $this->assertSame('new', $updated->getName());
        $this->assertSame(99, $updated->getQuantity());
        $this->assertDatabaseHas('br_test_widgets', ['id' => $widget->id, 'name' => 'new']);
    }

    public function test_update_where_returns_affected_count(): void
    {
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => false]);

        $affected = $this->repository->updateWhere(
            attributes: ['name' => 'renamed'],
            where: ['is_active' => true],
        );

        $this->assertSame(2, $affected);
        $this->assertSame(2, WidgetModel::query()->where('name', 'renamed')->count());
    }

    public function test_update_by_id_where_updates_when_predicate_matches(): void
    {
        $widget = $this->makeWidget(['is_active' => true, 'name' => 'old']);

        $updated = $this->repository->updateByIdWhere(
            id: $widget->id,
            attributes: ['name' => 'new'],
            where: ['is_active' => true],
        );

        $this->assertSame('new', $updated->getName());
    }

    public function test_update_by_id_where_throws_when_predicate_does_not_match(): void
    {
        $widget = $this->makeWidget(['is_active' => true]);

        $this->expectException(ModelNotFoundException::class);
        $this->repository->updateByIdWhere(
            id: $widget->id,
            attributes: ['name' => 'new'],
            where: ['is_active' => false],
        );
    }

    public function test_delete_by_id_soft_deletes_the_row(): void
    {
        $widget = $this->makeWidget();

        $this->assertTrue($this->repository->deleteById($widget->id));
        $this->assertSoftDeleted('br_test_widgets', ['id' => $widget->id]);
    }

    public function test_delete_where_returns_affected_count(): void
    {
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => true]);
        $this->makeWidget(['is_active' => false]);

        $deleted = $this->repository->deleteWhere(['is_active' => true]);

        $this->assertSame(2, $deleted);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  increment / decrement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_increment_bumps_an_integer_column(): void
    {
        $widget = $this->makeWidget(['quantity' => 10]);

        $this->repository->increment($widget->id, 'quantity', 3);

        $this->assertSame(13, (int) WidgetModel::query()->find($widget->id)->quantity);
    }

    public function test_increment_supports_float_amount(): void
    {
        $widget = $this->makeWidget(['price' => 10.00]);

        $this->repository->increment($widget->id, 'price', 2.50);

        $this->assertSame(12.50, (float) WidgetModel::query()->find($widget->id)->price);
    }

    public function test_decrement_lowers_an_integer_column(): void
    {
        $widget = $this->makeWidget(['quantity' => 10]);

        $this->repository->decrement($widget->id, 'quantity', 4);

        $this->assertSame(6, (int) WidgetModel::query()->find($widget->id)->quantity);
    }

    public function test_increment_where_bumps_matching_rows(): void
    {
        $a = $this->makeWidget(['quantity' => 1, 'is_active' => true]);
        $b = $this->makeWidget(['quantity' => 1, 'is_active' => true]);
        $c = $this->makeWidget(['quantity' => 1, 'is_active' => false]);

        $this->repository->incrementWhere(['is_active' => true], 'quantity', 5);

        $this->assertSame(6, (int) WidgetModel::query()->find($a->id)->quantity);
        $this->assertSame(6, (int) WidgetModel::query()->find($b->id)->quantity);
        $this->assertSame(1, (int) WidgetModel::query()->find($c->id)->quantity);
    }

    public function test_increment_each_updates_multiple_columns(): void
    {
        $widget = $this->makeWidget(['quantity' => 1, 'price' => 1.00]);

        $this->repository->incrementEach(
            columns: ['quantity' => 2, 'price' => 3.00],
            where: ['id' => $widget->id],
        );

        $fresh = WidgetModel::query()->find($widget->id);
        $this->assertSame(3, (int) $fresh->quantity);
        $this->assertSame(4.00, (float) $fresh->price);
    }

    public function test_decrement_each_updates_multiple_columns(): void
    {
        $widget = $this->makeWidget(['quantity' => 10, 'price' => 10.00]);

        $this->repository->decrementEach(
            where: ['id' => $widget->id],
            columns: ['quantity' => 2, 'price' => 1.00],
        );

        $fresh = WidgetModel::query()->find($widget->id);
        $this->assertSame(8, (int) $fresh->quantity);
        $this->assertSame(9.00, (float) $fresh->price);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Pagination
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paginate_returns_a_length_aware_paginator(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeWidget();
        }

        $page = $this->repository->paginate(limit: 2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertSame(5, $page->total());
        $this->assertCount(2, $page->items());
        $this->assertContainsOnlyInstancesOf(WidgetDomainObject::class, $page->items());
    }

    public function test_paginate_where_filters_then_paginates(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeWidget(['is_active' => true]);
        }
        $this->makeWidget(['is_active' => false]);

        $page = $this->repository->paginateWhere(['is_active' => true], limit: 2);

        $this->assertSame(3, $page->total());
        $this->assertCount(2, $page->items());
    }

    public function test_simple_paginate_where_returns_a_simple_paginator(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->makeWidget(['is_active' => true]);
        }

        $page = $this->repository->simplePaginateWhere(['is_active' => true], limit: 2);

        $this->assertInstanceOf(Paginator::class, $page);
        $this->assertCount(2, $page->items());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Eager loading
    // ─────────────────────────────────────────────────────────────────────────

    public function test_load_relation_hydrates_a_belongs_to_relation(): void
    {
        $category = $this->makeCategory('Tools');
        $widget = $this->makeWidget(['category_id' => $category->id]);

        $found = $this->repository
            ->loadRelation(new Relationship(WidgetCategoryDomainObject::class, name: 'category'))
            ->findById($widget->id);

        $this->assertNotNull($found->getCategory());
        $this->assertInstanceOf(WidgetCategoryDomainObject::class, $found->getCategory());
        $this->assertSame('Tools', $found->getCategory()->getName());
    }

    public function test_load_relation_hydrates_a_has_many_relation_as_a_collection(): void
    {
        $category = $this->makeCategory('Bolts');
        $this->makeWidget(['category_id' => $category->id, 'name' => 'M3']);
        $this->makeWidget(['category_id' => $category->id, 'name' => 'M4']);

        $found = $this->categoryRepository
            ->loadRelation(new Relationship(WidgetDomainObject::class, name: 'widgets'))
            ->findById($category->id);

        $this->assertInstanceOf(Collection::class, $found->getWidgets());
        $this->assertCount(2, $found->getWidgets());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Soft deletes / includeDeleted
    // ─────────────────────────────────────────────────────────────────────────

    public function test_include_deleted_returns_soft_deleted_rows(): void
    {
        $widget = $this->makeWidget();
        $this->repository->deleteById($widget->id);

        $this->assertNull($this->repository->findFirstWhere(['id' => $widget->id]));

        $found = $this->repository->includeDeleted()->findFirstWhere(['id' => $widget->id]);
        $this->assertNotNull($found);
        $this->assertSame($widget->id, $found->getId());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  State reset (the actual point of the refactor)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_consecutive_finds_do_not_leak_where_clauses(): void
    {
        $a = $this->makeWidget(['is_active' => true]);
        $b = $this->makeWidget(['is_active' => false]);

        // First call applies a where(is_active, true)
        $first = $this->repository->findWhere(['is_active' => true]);
        $this->assertCount(1, $first);

        // Second call must NOT inherit the previous where clause
        $second = $this->repository->findWhere([]);
        $this->assertCount(2, $second, 'Second findWhere([]) inherited state from the previous query');
    }

    public function test_eager_loads_are_reset_between_queries(): void
    {
        $category = $this->makeCategory('Cat');
        $widgetA = $this->makeWidget(['category_id' => $category->id]);
        $widgetB = $this->makeWidget(['category_id' => $category->id]);

        $first = $this->repository
            ->loadRelation(new Relationship(WidgetCategoryDomainObject::class, name: 'category'))
            ->findById($widgetA->id);
        $this->assertNotNull($first->getCategory());

        // After the call, eagerLoads MUST be cleared. Previously this was a bug —
        // resetModel() reset the builder but left $eagerLoads populated, so the
        // array would grow unboundedly across calls on the same instance.
        $this->assertSame([], $this->repository->exposeEagerLoads());

        // A subsequent call without loadRelation() must produce an unhydrated relation.
        $second = $this->repository->findById($widgetB->id);
        $this->assertNull($second->getCategory());
    }

    public function test_state_is_reset_even_when_the_query_throws(): void
    {
        $this->makeWidget(['is_active' => true]);

        try {
            // findById on a missing id throws ModelNotFoundException — but only
            // AFTER the loadRelation call has registered an eager load and added
            // a where clause.
            $this->repository
                ->loadRelation(new Relationship(WidgetCategoryDomainObject::class, name: 'category'))
                ->findById(999_999);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
            // expected
        }

        // The next call on the same repository instance must start clean.
        $this->assertSame([], $this->repository->exposeEagerLoads());
        $this->assertFalse($this->repository->exposeBuilderHasWheres());
    }

    public function test_set_max_per_page_caps_pagination_size(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->makeWidget();
        }

        $page = $this->repository->setMaxPerPage(3)->paginate(limit: 100);

        $this->assertCount(3, $page->items());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Hydration edge cases
    // ─────────────────────────────────────────────────────────────────────────

    public function test_hydration_calls_setters_via_studly_case(): void
    {
        // category_id is a snake_case column → setCategoryId on the domain object
        $category = $this->makeCategory();
        $widget = $this->makeWidget(['category_id' => $category->id]);

        $found = $this->repository->findById($widget->id);

        $this->assertSame($category->id, $found->getCategoryId());
    }

    public function test_hydration_silently_skips_columns_with_no_setter(): void
    {
        // No setter exists on WidgetDomainObject for an unknown column.
        // Add a column on the fly via raw SQL so the model picks it up.
        Schema::table('br_test_widgets', function (Blueprint $table) {
            $table->string('mystery_field')->nullable();
        });

        $widget = $this->makeWidget();
        WidgetModel::query()->where('id', $widget->id)->update(['mystery_field' => 'something']);

        // Should not throw — the silent-skip behaviour is documented.
        $found = $this->repository->findById($widget->id);
        $this->assertNotNull($found);
    }
}
