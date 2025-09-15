<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Type\AgreeToTermsType;
use App\Form\Type\RepeatedPasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('agreeTerms', AgreeToTermsType::class, [
                'mapped' => false,
            ])
            ->add('plainPassword', RepeatedPasswordType::class, [
                'mapped' => false,
                'sequentially_validation' => $options['password_sequentially_validation'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_sequentially_validation' => false,
        ]);

        $resolver->setAllowedTypes('password_sequentially_validation', 'bool');
    }
}
