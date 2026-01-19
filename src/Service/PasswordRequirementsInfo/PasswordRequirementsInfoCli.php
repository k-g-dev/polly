<?php

namespace App\Service\PasswordRequirementsInfo;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordRequirementsInfoCli extends AbstractPasswordRequirementsInfo
{
    public function __construct(
        int $minLength,
        string $specialChars,
        TranslatorInterface $translator,
        private ArrayInput $input,
        private BufferedOutput $output,
    ) {
        parent::__construct($minLength, $specialChars, $translator);
    }

    public function getInfoFull(): string
    {
        $io = new SymfonyStyle($this->input, $this->output);

        $infoParts = $this->prepareFullInfoParts();

        $io->text($infoParts['requirementsList']['header']);
        $io->listing($infoParts['requirementsList']['items']);
        $io->text([
            $infoParts['specialCharacters']['header'],
            sprintf('"%s"', $infoParts['specialCharacters']['set']),
        ]);

        return $this->output->fetch();
    }
}
