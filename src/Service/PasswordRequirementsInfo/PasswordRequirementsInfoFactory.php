<?php

namespace App\Service\PasswordRequirementsInfo;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class PasswordRequirementsInfoFactory
{
    public const FORMAT_CLI = 'cli';
    public const FORMAT_HTML = 'html';

    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        private int $minLength,
        #[Autowire(param: 'app.password.special_chars')]
        private string $specialChars,
        #[AutowireLocator([Environment::class])]
        private ContainerInterface $serviceLocator,
        private TranslatorInterface $translator,
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

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function createHtml(): PasswordRequirementsInfoHtml
    {
        return new PasswordRequirementsInfoHtml(
            $this->minLength,
            $this->specialChars,
            $this->translator,
            $this->serviceLocator->get(Environment::class),
        );
    }

    public function createCli(): PasswordRequirementsInfoCli
    {
        return new PasswordRequirementsInfoCli(
            $this->minLength,
            $this->specialChars,
            $this->translator,
            new ArrayInput([]),
            new BufferedOutput(),
        );
    }
}
