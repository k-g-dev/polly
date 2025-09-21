<?php

namespace App\Manager;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordManager $userPasswordManager,
    ) {
    }

    public function create(User $user, string $plainPassword, ?bool $agreeToTerms = false): void
    {
        if ($agreeToTerms) {
            $user->agreeToTerms();
        }

        $this->userPasswordManager->setHashedPassword($user, $plainPassword);

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
