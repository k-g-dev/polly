<?php

namespace App\Manager;

use App\Entity\User;
use App\Form\Model\UserRegistration;
use Doctrine\ORM\EntityManagerInterface;

class UserManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordManager $userPasswordManager,
    ) {
    }

    public function create(UserRegistration $dto, User $user = null): User
    {
        $user ??= new User();
        $user->setEmail($dto->email);

        if ($dto->agreeTerms) {
            $user->agreeToTerms();
        }

        $this->userPasswordManager->setHashedPassword($user, $dto->password->plainPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function agreeToTerms(User $user): void
    {
        if ($user->hasAgreedToTerms()) {
            return;
        }

        $user->agreeToTerms();

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
