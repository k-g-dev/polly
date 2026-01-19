<?php

namespace App\Service\PasswordRequirementsInfo;

use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractPasswordRequirementsInfo implements PasswordRequirementsInfoInterface
{
    public function __construct(
        protected int $minLength,
        protected string $specialChars,
        private TranslatorInterface $translator,
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
        return $this->translator->trans('password_requirements_info.short', [
            '%min_number_of_chars%' => $this->getTranslatedMinNumberOfChars(),
        ], 'services');
    }

    protected function prepareFullInfoParts(): array
    {
        return [
            'requirementsList' => [
                'header' => $this->translator->trans('password_requirements_info.full.header', [
                        '%min_number_of_chars%' => $this->getTranslatedMinNumberOfChars(),
                    ], 'services'),
                'items' => [
                    $this->translator
                        ->trans('password_requirements_info.full.requirements.lowercase_letter', domain: 'services'),
                    $this->translator
                        ->trans('password_requirements_info.full.requirements.uppercase_letter', domain: 'services'),
                    $this->translator
                        ->trans('password_requirements_info.full.requirements.digit', domain: 'services'),
                    $this->translator
                        ->trans('password_requirements_info.full.requirements.special_character', domain: 'services'),
                ],
            ],
            'specialCharacters' => [
                'header' => $this->translator
                    ->trans('password_requirements_info.full.special_characters.header', domain: 'services'),
                'set' => $this->specialChars,
            ],
        ];
    }

    private function getTranslatedMinNumberOfChars(): string
    {
        return $this->translator->trans('character', ['%count%' => $this->minLength], 'units');
    }
}
