<?php

namespace HiEvents\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class AllowedForReadOnlyUsers {}
