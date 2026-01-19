<?php

namespace App\Tests\Integration\Service\EmailSender;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Service\EmailSender\ConfirmationEmailSender;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ConfirmationEmailSenderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSendEmailWithProperData(): void
    {
        $container = static::getContainer();
        $confirmationEmailSender = $container->get(ConfirmationEmailSender::class);
        $translator = $container->get(TranslatorInterface::class);

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
        $emailFrom = $container->getParameter('app.email.from');

        self::assertEmailAddressContains($templatedEmail, 'from', $emailFrom);
        self::assertEmailAddressContains($templatedEmail, 'to', $user->getEmail());

        // Get the verification link from the email.
        preg_match('#"(.+/verification/account/email.+)">#', $templatedEmail->getHtmlBody(), $verificationLink);

        self::assertEmailHtmlBodyContains($templatedEmail, $verificationLink[1]);
        self::assertEmailHtmlBodyContains(
            $templatedEmail,
            $translator->trans('link.expiration_info', [
                '%expiration_time%' => $translator->trans('date_time.hour', ['hour' => 1], 'units'),
            ], 'messages'),
        );
    }
}
