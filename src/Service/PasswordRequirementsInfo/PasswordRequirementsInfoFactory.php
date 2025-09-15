<?php

namespace App\Service\PasswordRequirementsInfo;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PasswordRequirementsInfoFactory
{
    public const FORMAT_CLI = 'cli';
    public const FORMAT_HTML = 'html';

    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        private int $minLength,
        #[Autowire(param: 'app.password.special_chars')]
        private string $specialChars,
    ) {
    }

    public function create(string $format): PasswordRequirementsInfoInterface
    {
        return match ($format) {
            self::FORMAT_HTML => $this->createHtml(),
            self::FORMAT_CLI => $this->createCli(),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    public function createHtml(): PasswordRequirementsInfoHtml
    {
        return new PasswordRequirementsInfoHtml($this->minLength, $this->specialChars);
    }

    public function createCli(): PasswordRequirementsInfoCli
    {
        return new PasswordRequirementsInfoCli(
            $this->minLength,
            $this->specialChars,
            new ArrayInput([]),
            new BufferedOutput(),
        );
    }
}
