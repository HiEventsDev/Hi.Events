<?php

use Illuminate\Support\Facades\Artisan;
use TicketKitten\Service\Common\DomainObjectGenerator\ClassGenerator;

Artisan::command('generate-domain-objects',
    fn() => app()->make(ClassGenerator::class)->run()
)->describe('Generate domain objects from db');
