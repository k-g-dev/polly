<?php

namespace App\Tests\Integration\Manager;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Manager\UserManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserManagerTest extends KernelTestCase
{
    use ResetDatabase;

    public function testCreateHashesUserPassword(): void
    {
        $container = static::getContainer();
        $userManager = $container->get(UserManager::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('me@example.com');
        $plainPassword = UserFactory::USER_DEFAULT_PASSWORD;

        $userManager->create($user, $plainPassword);

        $hashedPassword = $user->getPassword();

        self::assertNotSame($plainPassword, $hashedPassword, 'Password should be hashed.');
        self::assertTrue($passwordHasher->isPasswordValid($user, $plainPassword), 'Hashed password is not valid.');
    }
}
