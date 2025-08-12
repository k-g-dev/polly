<?php

namespace App\Form\Type;

use App\Validator\Constraints\PasswordRequirements;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepeatedPasswordType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'app.password.min_length')] private int $passwordMinLength,
    ) {
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['passwordMinLength'] = $this->passwordMinLength;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'The passwords do not match.',
            'sequentially_validation' => false,
        ]);

        $this->configureConstraintsOption($resolver);
        $this->configureFirstOptionsOption($resolver);
        $this->configureSecondOptionsOption($resolver);

        $resolver->setAllowedTypes('sequentially_validation', 'bool');
    }

    private function configureConstraintsOption(OptionsResolver $resolver): void
    {
        $resolver->setDefault('constraints', function (Options $options): array {
            return [
                new PasswordRequirements(
                    options: [
                        'passwordMinLength' => $this->passwordMinLength,
                        'sequentiallyValidation' => $options['sequentially_validation'],
                    ],
                ),
            ];
        });
    }

    private function configureFirstOptionsOption(OptionsResolver $resolver): void
    {
        $resolver->setOptions('first_options', function (OptionsResolver $optionsResolver): void {
            $optionsResolver->setDefaults([
                'label' => 'Password',
                'help' => "Your password must be at least {$this->passwordMinLength} characters long.",
            ]);
        });
    }

    private function configureSecondOptionsOption(OptionsResolver $resolver): void
    {
        $resolver->setOptions('second_options', function (OptionsResolver $optionsResolver): void {
            $optionsResolver->setDefaults([
                'label' => 'Repeat Password',
                'help' => null,
            ]);
        });
    }
}
