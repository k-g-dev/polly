<?php

namespace App\Manager;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function create(User $user, string $plainPassword, ?bool $agreeToTerms = false): void
    {
        if ($agreeToTerms) {
            $user->agreeToTerms();
        }

        $user->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
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
