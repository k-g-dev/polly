<?php

namespace App\Form;

use App\Form\Model\PasswordChange;
use App\Form\Type\PasswordRepeatedType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordChangeFormType extends AbstractType
{
    public function __construct(
        #[Autowire(param: 'app.password.min_length')]
        private int $passwordMinLength,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('oldPassword', PasswordType::class, [
                'label' => 'Current password',
            ])
            ->add('newPassword', PasswordRepeatedType::class, [
                'first_options' => ['label' => 'New password'],
                'second_options' => ['label' => 'Repeat new password'],
                'password_min_length' => $options['password_min_length'],
                'constraints' => [],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => PasswordChange::class,
                'empty_data' => function (FormInterface $form): PasswordChange {
                    return new PasswordChange(
                        $form->getConfig()->getOption('password_min_length'),
                    );
                },
                'password_min_length' => $this->passwordMinLength,
            ])
        ;

        $resolver->setAllowedTypes('password_min_length', 'int');
    }
}
