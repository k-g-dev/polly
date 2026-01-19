<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['autocomplete' => 'email'],
                'label' => new TranslatableMessage(
                    'form.password_reset_request.field.email.label',
                    domain: 'forms',
                ),
                'help' => new TranslatableMessage(
                    'form.password_reset_request.field.email.help',
                    domain: 'forms',
                ),
                'constraints' => [
                    new Assert\NotBlank(message: 'user.email.not_blank'),
                    new Assert\Email(),
                ],
            ])
        ;
    }
}
