<?php

namespace App\Modules\Authentication\Enums;

enum LoginEvent: string
{
    case LOGIN = 'login';
    case FAILED_LOGIN = 'failed_login';
    case LOGOUT = 'logout';
    case LOGOUT_ALL = 'logout_all';
    case PASSWORD_CHANGE = 'password_change';
    case PASSWORD_RESET = 'password_reset';
}
