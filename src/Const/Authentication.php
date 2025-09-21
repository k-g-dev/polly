<?php

namespace App\Const;

class Authentication
{
    /**
     * A session key used to store the email address of a user whose account has not been verified.
     */
    public const NON_VERIFIED_EMAIL = 'authentication.non_verified_email';

    public const PASSWORD_SPECIAL_CHARACTERS = ' !"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~';
}
