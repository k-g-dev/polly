<?php

namespace App\Manager;

use App\Entity\User;
use App\Form\Model\PasswordChange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function setHashedPassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword));
    }

    public function changePassword(User $user, PasswordChange $passwordChangeModel): void
    {
        $this->setHashedPassword($user, $passwordChangeModel->newPassword);

        $this->entityManager->flush();
    }
}
