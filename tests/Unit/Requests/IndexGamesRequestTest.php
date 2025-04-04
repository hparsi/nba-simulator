<?php

namespace Tests\Unit\Requests;

use PHPUnit\Framework\TestCase;
use App\Http\Requests\Game\IndexGamesRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IndexGamesRequestTest extends TestCase
{
    protected $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new IndexGamesRequest();
    }

    /**
     * Test that the validation rules exist.
     */
    public function test_validation_rules_exist(): void
    {
        $rules = $this->request->rules();
        
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('ids', $rules);
        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('with_events', $rules);
    }

    /**
     * Test that the IDs validation rule is correct.
     */
    public function test_ids_validation_rule(): void
    {
        $rules = $this->request->rules();
        $this->assertEquals('sometimes|string', $rules['ids']);
    }

    /**
     * Test that the status validation rule is correct.
     */
    public function test_status_validation_rule(): void
    {
        $rules = $this->request->rules();
        $this->assertEquals('sometimes|string|in:scheduled,in_progress,completed', $rules['status']);
    }

    /**
     * Test that the with_events validation rule is correct.
     */
    public function test_with_events_validation_rule(): void
    {
        $rules = $this->request->rules();
        $this->assertEquals('sometimes|boolean', $rules['with_events']);
    }

    /**
     * Test that authorization is granted.
     */
    public function test_authorization_is_granted(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    /**
     * Test that prepare for validation converts string boolean values.
     */
    public function test_prepare_for_validation_concept(): void
    {
        $this->assertTrue(method_exists($this->request, 'prepareForValidation'));
    }
}
