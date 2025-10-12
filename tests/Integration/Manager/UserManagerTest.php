<?php

namespace App\Tests\Integration\Manager;

use App\Factory\UserFactory;
use App\Form\Model\Password;
use App\Form\Model\UserRegistration;
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
        $passwordMinLength = $container->getParameter('app.password.min_length');

        $passwordDto = new Password($passwordMinLength);
        $passwordDto->plainPassword = UserFactory::USER_DEFAULT_PASSWORD;

        $userRegistrationDto = new UserRegistration();
        $userRegistrationDto->email = 'me@example.com';
        $userRegistrationDto->password = $passwordDto;

        $user = $userManager->create($userRegistrationDto);

        $hashedPassword = $user->getPassword();

        self::assertNotSame(
            $userRegistrationDto->password->plainPassword,
            $hashedPassword,
            'Password should be hashed.',
        );

        self::assertTrue(
            $passwordHasher->isPasswordValid($user, $userRegistrationDto->password->plainPassword),
            'Hashed password is not valid.',
        );
    }
}
