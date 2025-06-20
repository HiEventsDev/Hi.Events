<?php

namespace HiEvents\Validators\Rules;

class RulesHelper
{
    public const MONEY = ['gte:0', 'numeric', 'decimal:0,2', 'max:999999999999'];

    public const REQUIRED_STRING = ['string' , 'required', 'max:100', 'min:1'];

    public const REQUIRED_NUMERIC= ['numeric' , 'required'];

    public const STRING = ['string', 'max:100', 'min:1', 'nullable'];

    public const HEX_COLOR = ['string', 'max:9', 'min:4', 'regex:/\#(?:[0-9a-fA-F]{3}){1,2}$|^\#(?:[0-9a-fA-F]{4}){1,2}$/'];

    public const REQUIRED_EMAIL = ['email' , 'required', 'max:100'];

    public const OPTIONAL_TEXT_MEDIUM_LENGTH = ['string', 'max:2000', 'nullable'];

    public const IMAGE_RULES = [
        'required',
        'image',
        'max:8192', //8mb
        'dimensions:min_width=600,min_height=50,max_width=4000,max_height=4000',
        'mimes:jpeg,png,jpg,webp',
    ];
}
