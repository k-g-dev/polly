<?php

namespace App\Service\PasswordRequirementsInfo;

abstract class AbstractPasswordRequirementsInfo implements PasswordRequirementsInfoInterface
{
    public function __construct(
        protected int $minLength,
        protected string $specialChars,
    ) {
    }

    public function setMinLength(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function setSpecialChars(string $specialChars): static
    {
        $this->specialChars = $specialChars;

        return $this;
    }

    public function getInfoShort(): string
    {
        return "Your password must be at least {$this->minLength} characters long.";
    }

    protected function prepareFullInfoParts(): array
    {
        return [
            'requirementsList' => [
                'header' => "Your password must be at least {$this->minLength} characters long"
                    . " and include a combination of at least one:",
                'items' => [
                    'lowercase letter,',
                    'uppercase letter,',
                    'digit,',
                    'symbol from a defined set of special characters.',
                ],
            ],
            'specialCharacters' => [
                'header' => 'Definied set of special characters (listed as string between double quotes):',
                'set' => $this->specialChars,
            ],
        ];
    }
}
