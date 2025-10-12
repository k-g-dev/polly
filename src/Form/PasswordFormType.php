<?php

namespace App\Form;

use App\Form\Model\Password;
use App\Form\Type\PasswordRepeatedType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordFormType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        private int $passwordMinLength,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', PasswordRepeatedType::class, [
                'first_options' => $options['first_options'] ?? [],
                'second_options' => $options['second_options'] ?? [],
                'password_min_length' => $options['password_min_length'],
                'sequentially_validation' => $options['sequentially_validation'],
                'constraints' => [],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Password::class,
            'empty_data' => function (FormInterface $form): Password {
                return new Password(
                    $form->getConfig()->getOption('password_min_length'),
                    $form->getConfig()->getOption('sequentially_validation'),
                );
            },
            'password_min_length' => $this->passwordMinLength,
            'sequentially_validation' => false,
        ]);

        $resolver->setDefined(['first_options', 'second_options']);

        $resolver
            ->setAllowedTypes('first_options', 'array')
            ->setAllowedTypes('second_options', 'array')
            ->setAllowedTypes('sequentially_validation', 'bool');
    }
}
