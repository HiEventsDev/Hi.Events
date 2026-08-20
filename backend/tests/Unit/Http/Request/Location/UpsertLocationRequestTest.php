<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\Location;

use HiEvents\Http\Request\Location\UpsertLocationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpsertLocationRequestTest extends TestCase
{
    public function test_rejects_unknown_provider(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall'],
            'provider' => 'foo',
            'provider_place_id' => 'some_id',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has('provider'));
    }

    public function test_accepts_google_provider(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall'],
            'provider' => 'google',
            'provider_place_id' => 'ChIJsomething',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->errors()->has('provider'));
    }

    public function test_provider_without_place_id_is_rejected(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall'],
            'provider' => 'google',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has('provider_place_id'));
    }

    public function test_place_id_without_provider_is_rejected(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall'],
            'provider_place_id' => 'ChIJsomething',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has('provider'));
    }

    public function test_both_null_is_allowed(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall'],
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->errors()->has('provider'));
        $this->assertFalse($validator->errors()->has('provider_place_id'));
    }

    public function test_country_must_be_exactly_two_characters(): void
    {
        foreach (['I', 'IRL'] as $invalidCountry) {
            $request = new UpsertLocationRequest;
            $request->merge([
                'structured_address' => ['venue_name' => 'Foo Hall', 'country' => $invalidCountry],
            ]);

            $validator = Validator::make($request->all(), $request->rules());

            $this->assertTrue($validator->errors()->has('structured_address.country'));
        }
    }

    public function test_two_character_country_is_accepted(): void
    {
        $request = new UpsertLocationRequest;
        $request->merge([
            'structured_address' => ['venue_name' => 'Foo Hall', 'country' => 'ie'],
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->errors()->has('structured_address.country'));
    }
}
