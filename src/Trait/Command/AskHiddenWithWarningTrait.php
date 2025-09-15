<?php

namespace App\Trait\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

trait AskHiddenWithWarningTrait
{
    /**
     * Create hidden input with a warning about the potential risk of input visibility in Windows.
     *
     * In Windows, hidden inputs behave differently. The entered data will be visible if hiding fails.
     */
    public function askHiddenWithWarning(SymfonyStyle $io, string $question, ?callable $validator = null): mixed
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $io->warning('Entered value may be visible due to terminal limitations on Windows.');
        }

        return $io->askHidden($question, $validator);
    }
}
