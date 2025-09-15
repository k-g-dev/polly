<?php

namespace App\Service\PasswordRequirementsInfo;

class PasswordRequirementsInfoHtml extends AbstractPasswordRequirementsInfo
{
    public function getInfoFull(): string
    {
        $infoParts = $this->prepareFullInfoParts();

        return <<<TEXT
            {$infoParts['requirementsList']['header']}
            <ul>
                {$this->getListItems($infoParts['requirementsList']['items'])}
            </ul>
            <p class="mb-0">
                {$infoParts['specialCharacters']['header']}<br>
                "<span class="text-success font-ibm-plex-mono">{$infoParts['specialCharacters']['set']}</span>"
            </p>
        TEXT;
    }

    private function getListItems(array $items): string
    {
        return array_reduce(
            $items,
            fn(string $carry, string $item): string => $carry .= "<li>{$item}</li>",
            '',
        );
    }
}
