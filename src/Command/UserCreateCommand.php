<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\AuthorizationRole;
use App\Form\Model\Password;
use App\Form\Model\UserRegistration;
use App\Helper\Validator\ValidationHelper;
use App\Helper\Validator\ValidatorHelper;
use App\Manager\UserManager;
use App\Service\PasswordRequirementsInfo\PasswordRequirementsInfoInterface;
use App\Trait\Command\AskHiddenWithWarningTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a new user',
    help: 'This command allows you to create a user with the chosen roles.',
)]
class UserCreateCommand extends Command
{
    use AskHiddenWithWarningTrait;

    public function __construct(
        private UserManager $userManager,
        #[Autowire(param: 'app.password.min_length')]
        private int $passwordMinLength,
        private ValidatorHelper $validatorHelper,
        private ValidationHelper $validationHelper,
        private PasswordRequirementsInfoInterface $passwordRequirementsInfo,
    ) {
        parent::__construct();

        $this->passwordRequirementsInfo->setMinLength($this->passwordMinLength);
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $userRegistrationDto = new UserRegistration();

        $io->title('New user creator');

        $io->ask('Enter email', null, $this->validatorHelper->createPropertyValueValidatorCallable(
            $userRegistrationDto,
            'email',
        ));

        $io->section('Password');

        $io->comment($this->passwordRequirementsInfo->getInfoShort());

        if ($io->confirm('Would you like to see full password requirements?', false)) {
            $io->text($this->passwordRequirementsInfo->getInfoFull());
        }

        $passwordDto = new Password($this->passwordMinLength);
        $this->askHiddenWithWarning($io, 'Enter password', $this->validatorHelper->createPropertyValueValidatorCallable(
            $passwordDto,
            'plainPassword',
        ));

        $io->askHidden('Repeat password', $this->validationHelper->createCallable(
            new NotBlank(),
            new IdenticalTo(value: $passwordDto->plainPassword, message: 'The passwords do not match.'),
        ));

        $userRegistrationDto->password = $passwordDto;

        $io->section('Other settings');

        $userRegistrationDto->agreeTerms = $io->confirm('Agree to terms?', false);

        $roles = $io->choice(
            'Choose authorization roles',
            AuthorizationRole::values(),
            AuthorizationRole::User->value,
            true,
        );

        $io->section('Summary');

        $io->definitionList(
            ['Email' => $userRegistrationDto->email],
            ['Agree to terms' => $userRegistrationDto->agreeTerms ? 'yes' : 'no'],
            ['Roles' => implode(', ', $roles)],
        );

        if ($io->confirm('Do you want to create this user?', true)) {
            $user = (new User())->setRoles(...AuthorizationRole::tryFromMultiple(...$roles));

            $this->userManager->create($userRegistrationDto, $user);

            $io->success('The user has been created.');
        } else {
            $io->note('Operation canceled.');
        }

        return Command::SUCCESS;
    }
}
