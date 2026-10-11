<?php

namespace App\Form;

use App\Form\Model\UserRegistration;
use App\Form\Type\AgreeToTermsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', options: [
                'label' => new TranslatableMessage('form.registration.field.email.label', domain: 'forms'),
            ])
            ->add('agreeTerms', AgreeToTermsType::class, [
                'constraints' => [],
            ])
            ->add('password', PasswordFormType::class, [
                'label' => false,
                'sequentially_validation' => $options['password_sequentially_validation'],
                'extended_validation' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserRegistration::class,
            'empty_data' => fn(): UserRegistration => new UserRegistration(),
            'password_sequentially_validation' => false,
        ]);

        $resolver->setAllowedTypes('password_sequentially_validation', 'bool');
    }
}
