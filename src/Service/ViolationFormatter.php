<?php

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ViolationFormatter
{
    public function __construct(
        private TranslatorInterface $translator,
        private string $defaultDomain = 'validators',
    ) {
    }

    public function toArray(
        ConstraintViolationListInterface $violations,
        ?string $domain = null,
        ?string $locale = null,
    ): array {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $this->translator->trans(
                $violation->getMessageTemplate(),
                $violation->getParameters(),
                $domain ?? $this->defaultDomain,
                $locale,
            );
        }

        return $messages;
    }

    public function toString(
        ConstraintViolationListInterface $violations,
        ?string $domain = null,
        ?string $locale = null,
        string $separator = "\n",
    ): string {
        return \implode($separator, $this->toArray($violations, $domain, $locale));
    }
}
