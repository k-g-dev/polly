<?php

namespace App\Security\RequestMatcher;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class LocaleAwarePathMatcher implements RequestMatcherInterface
{
    private string $path;

    public function __construct(
        #[Autowire(param: 'app.enabled_locales')]
        private array $enabledLocales,
        string $path,
    ) {
        $this->path = ltrim($path, '/');
    }

    public function matches(Request $request): bool
    {
        $pattern = sprintf(
            '#^/(?:%s/)?%s#',
            implode('|', $this->enabledLocales),
            $this->path,
        );

        return (bool) preg_match($pattern, $request->getPathInfo());
    }
}
