<?php

namespace App\Service\PasswordRequirementsInfo;

interface PasswordRequirementsInfoInterface
{
    public function getInfoShort(): string;
    public function getInfoFull(): string;

    public function setMinLength(int $length): static;
    public function setSpecialChars(string $specialChars): static;
}
