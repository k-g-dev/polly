<?php

namespace App\Form;

use App\Form\Type\AgreeToTermsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class AgreeToTermsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('agreeTerms', AgreeToTermsType::class, [
                'mapped' => false,
            ])
        ;
    }
}
