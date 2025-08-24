<?php

namespace App\Tests\Integration\Service;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Service\ConfirmationEmailSender;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ConfirmationEmailSenderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSendEmailWithProperData(): void
    {
        $confirmationEmailSender = static::getContainer()->get(ConfirmationEmailSender::class);

        /** @var User $user */
        $user = UserFactory::createOne([
            'isVerified' => false,
        ]);

        /** @var ConfirmationEmailSender $confirmationEmailSender */
        $confirmationEmailSender->send($user);

        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $emailFrom = static::getContainer()->getParameter('app.email_from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', $user->getEmail());
        self::assertEmailTextBodyContains($templatedEmail, 'This link will expire in 1 hour.');
    }
}
