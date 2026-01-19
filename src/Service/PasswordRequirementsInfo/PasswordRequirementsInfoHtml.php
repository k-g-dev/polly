<?php

namespace App\Service\PasswordRequirementsInfo;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class PasswordRequirementsInfoHtml extends AbstractPasswordRequirementsInfo
{
    public function __construct(
        int $minLength,
        string $specialChars,
        TranslatorInterface $translator,
        private Environment $twig,
    ) {
        parent::__construct($minLength, $specialChars, $translator);
    }

    public function getInfoFull(): string
    {
        return $this->twig->render('service/password_requirements_info/info_full.html.twig', [
            'infoParts' => $this->prepareFullInfoParts(),
        ]);
    }
}
