<?php

namespace App\Enums;

class ResponseCode
{
    // Success codes
    public const REGISTER_SUCCESS = 'REGISTER_SUCCESS';
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const LOGOUT_SUCCESS = 'LOGOUT_SUCCESS';
    public const SUCCESS = 'SUCCESS';
    public const COMPANY_CREATED = 'COMPANY_CREATED';
    public const COMPANY_UPDATED = 'COMPANY_UPDATED';
    public const COMPANY_DELETED = 'COMPANY_DELETED';
    
    // Error codes
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const UNAUTHORIZED = 'UNAUTHORIZED';
}

