<?php

namespace App\Form\Type;

use App\Service\PasswordRequirementsInfo\PasswordRequirementsInfoInterface;
use App\Validator\PasswordRequirements;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordRepeatedType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        private int $passwordMinLength,
        private PasswordRequirementsInfoInterface $passwordRequirementsInfo,
    ) {
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->passwordRequirementsInfo->setMinLength($options['password_min_length']);

        $view->children['first']->vars['help'] ??= $this->passwordRequirementsInfo->getInfoShort();

        $view->vars['passwordRequirementsInfoFull'] = $this->passwordRequirementsInfo->getInfoFull();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'The passwords do not match.',
            'password_min_length' => $this->passwordMinLength,
            'sequentially_validation' => false,
            'constraints' => fn(Options $options): array => [
                new PasswordRequirements($options['password_min_length'], $options['sequentially_validation']),
            ],
        ])
        ->setOptions('first_options', function (OptionsResolver $resolver): void {
            $resolver->setDefaults([
                'label' => 'Password',
                'help' => null,
            ]);
        })
        ->setOptions('second_options', function (OptionsResolver $resolver): void {
            $resolver->setDefaults([
                'label' => 'Repeat password',
                'help' => null,
            ]);
        });

        $resolver->setAllowedTypes('password_min_length', 'int');
        $resolver->setAllowedTypes('sequentially_validation', 'bool');
    }
}
