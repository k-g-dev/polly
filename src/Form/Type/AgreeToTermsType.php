<?php

namespace App\Form\Type;

use App\Controller\MainController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class AgreeToTermsType extends AbstractType
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
        private \Symfony\Contracts\Translation\TranslatorInterface $translator,
    ) {
    }

    public function getParent(): string
    {
        return CheckboxType::class;
    }

    /**
     * @throws RouteNotFoundException
     * @throws MissingMandatoryParametersException
     * @throws InvalidParameterException
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Assert\IsTrue(message: 'user.terms.agree'),
            ],
            'label' => new TranslatableMessage('type.agree_to_terms.field.agree_to_terms.label', domain: 'forms'),
            'help' => $this->getDefaultHelpValue(),
            'help_html' => true,
        ]);
    }

    /**
     * @throws RouteNotFoundException
     * @throws MissingMandatoryParametersException
     * @throws InvalidParameterException
     */
    private function getDefaultHelpValue(): TranslatableMessage
    {
        $action = $this->security->isGranted('IS_AUTHENTICATED')
            ? new TranslatableMessage('type.agree_to_terms.account.action.use', domain: 'forms')
            : new TranslatableMessage('type.agree_to_terms.account.action.create', domain: 'forms');

        $termsLink = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            $this->urlGenerator->generate(MainController::ROUTE_TERMS_OF_SERVICE),
            $this->translator->trans('type.agree_to_terms.account.terms.link_text', domain: 'forms'),
        );

        return new TranslatableMessage('type.agree_to_terms.account.terms.agreement', [
            '%action%' => $action,
            '%terms_link%' => $termsLink,
        ], 'forms');
    }
}
