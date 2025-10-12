<?php

namespace App\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmailSenderConfig
{
    public function __construct(
        #[Autowire(param: 'app.email.from')]
        public readonly string $emailFrom,
        #[Autowire(param: 'app.email.name')]
        public readonly string $emailName,
    ) {
    }
}
