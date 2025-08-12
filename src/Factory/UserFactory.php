<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

use function Zenstruck\Foundry\force;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct(
        private ?UserPasswordHasherInterface $passwordHasher = null,
    ) {
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        $createdAt = self::faker()->dateTimeBetween('-1 year', '-1 month');
        $updatedAt = self::faker()->boolean(30)
            ? self::faker()->dateTimeBetween($createdAt, 'now')
            : clone $createdAt;

        return [
            'agreedTermsAt' => force(\DateTimeImmutable::createFromMutable($createdAt)),
            'createdAt' => $createdAt,
            'email' => self::faker()->unique()->safeEmail(),
            'isVerified' => self::faker()->boolean(),
            'password' => 'userPassword#001',
            'roles' => [],
            'updatedAt' => $updatedAt,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (User $user): void {
                if ($this->passwordHasher !== null) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
                }
            });
    }
}
