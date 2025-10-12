<?php

namespace App\Tests\Integration\Service\EmailSender;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Service\EmailSender\PasswordResetEmailSender;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PasswordResetEmailSenderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSendEmailWithProperData(): void
    {
        $container = static::getContainer();
        $emailSender = $container->get(PasswordResetEmailSender::class);

        /** @var User $user */
        $user = UserFactory::createOne();
        $token = 'fakeToken123';
        $resetPasswordToken = new ResetPasswordToken($token, new \DateTimeImmutable('+1 hour'), time());

        /** @var PasswordResetEmailSender $emailSender */
        $emailSender->send($user, [
            'resetToken' => $resetPasswordToken,
        ]);

        self::assertEmailCount(1);

        $messages = $this->getMailerMessages();
        self::assertCount(1, $messages);

        /** @var TemplatedEmail $templatedEmail */
        $templatedEmail = $messages[0];
        $emailFrom = $container->getParameter('app.email.from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', $user->getEmail());

        preg_match("#(/reset-password/reset/{$token})#", $templatedEmail->toString(), $resetLink);

        self::assertEmailTextBodyContains($templatedEmail, $resetLink[1]);
        self::assertEmailTextBodyContains($templatedEmail, 'This link will expire in');
    }
}
