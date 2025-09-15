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
use Symfony\Component\Validator\Constraints\IsTrue;

class AgreeToTermsType extends AbstractType
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
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
                new IsTrue(message: 'You should agree to our terms.'),
            ],
            'help' => $this->getDefaultHelpValue(),
            'help_html' => true,
        ]);
    }

    /**
     * @throws RouteNotFoundException
     * @throws MissingMandatoryParametersException
     * @throws InvalidParameterException
     */
    private function getDefaultHelpValue(): string
    {
        return sprintf(
            '%s, you must agree to the %s',
            $this->security->isGranted('IS_AUTHENTICATED')
                ? 'To use your user account'
                : 'To create an account',
            $this->getLinkToTermsOfService(),
        );
    }

    /**
     * @throws RouteNotFoundException
     * @throws MissingMandatoryParametersException
     * @throws InvalidParameterException
     */
    private function getLinkToTermsOfService(): string
    {
        return sprintf(
            '<a href="%s" target="_blank">Terms of Service</a>',
            $this->urlGenerator->generate(MainController::ROUTE_TERMS_OF_SERVICE),
        );
    }
}
