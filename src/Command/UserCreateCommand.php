<?php

namespace App\Command;

use App\Const\Authentication;
use App\Entity\User;
use App\Enum\AuthorizationRole;
use App\Helper\Validator\ValidationHelper;
use App\Helper\Validator\ValidatorHelper;
use App\Manager\UserManager;
use App\Service\PasswordRequirementsInfo\PasswordRequirementsInfoInterface;
use App\Trait\Command\AskHiddenWithWarningTrait;
use App\Validator\PasswordRequirements;
use App\Validator\UserEmailUnique;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

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
        $io->title('New user creator');

        $email = $io->ask('Enter email', null, $this->validatorHelper->createCallable(
            new Sequentially([
                new NotBlank(),
                new Email(),
                new UserEmailUnique(),
            ]),
        ));

        $io->section('Password');

        $io->comment($this->passwordRequirementsInfo->getInfoShort());

        if ($io->confirm('Would you like to see full password requirements?', false)) {
            $io->text($this->passwordRequirementsInfo->getInfoFull());
        }

        $plainPassword = $this->askHiddenWithWarning($io, 'Enter password', $this->validationHelper->createCallable(
            new PasswordRequirements([
                'passwordMinLength' => $this->passwordMinLength,
                'sequentiallyValidation' => true,
            ]),
        ));

        $io->askHidden('Repeat password', $this->validationHelper->createCallable(
            new NotBlank(),
            new IdenticalTo(value: $plainPassword, message: 'The passwords do not match.'),
        ));

        $io->section('Other settings');

        $agreeToTerms = $io->confirm('Agree to terms?', false);

        $roles = $io->choice(
            'Choose authorization roles',
            AuthorizationRole::values(),
            AuthorizationRole::User->value,
            true,
        );

        $io->section('Summary');

        $io->definitionList(
            ['Email' => $email],
            ['Agree to terms' => $agreeToTerms ? 'yes' : 'no'],
            ['Roles' => implode(', ', $roles)],
        );

        if ($io->confirm('Do you want to create this user?', true)) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(...AuthorizationRole::tryFromMultiple(...$roles));

            $this->userManager->create($user, $plainPassword, $agreeToTerms);

            $io->success('The user has been created.');
        } else {
            $io->note('Operation canceled.');
        }

        return Command::SUCCESS;
    }
}
