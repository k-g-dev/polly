<?php

namespace App\Form;

use App\Entity\User;
use App\Form\Type\RepeatedPasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\IsTrue;

class RegistrationFormType extends AbstractType
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $linkTermsOfService = sprintf(
            '<a href="%s" target="_blank">Terms of Service</a>',
            $this->urlGenerator->generate('app_terms_of_service'),
        );

        $builder
            ->add('email')
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'You should agree to our terms.'),
                ],
                'help' => "To create an account, you must agree to the {$linkTermsOfService}.",
                'help_html' => true,
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
