<?php

use HiEvents\Services\Infrastructure\DomainObjectGenerator\ClassGenerator;
use Illuminate\Support\Facades\Artisan;

Artisan::command('generate-domain-objects',
    fn() => app()->make(ClassGenerator::class)->run()
)->describe('Generate domain objects from db');
