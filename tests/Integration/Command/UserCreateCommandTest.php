<?php

namespace App\Tests\Integration\Command;

use App\Entity\User;
use App\Enum\AuthorizationRole;
use App\Factory\UserFactory;
use App\Form\Model\UserRegistration;
use App\Manager\UserManager;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Constraints as Assert;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The tests are skipped on Windows because on this OS, the askHidden() function does not use an Input object,
 * but a special binary. If the test were run on Windows, the askHidden() would wait indefinitely for user input.
 * See each test for skip marker.
 */
final class UserCreateCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const HAPPY_PATH_INPUT_DATA = [
        'email' => 'me@example.com',
        'showFullPasswordRequirements' => 'yes',
        'password' => UserFactory::USER_DEFAULT_PASSWORD,
        'passwordRepeated' => UserFactory::USER_DEFAULT_PASSWORD,
        'agreeTerms' => 'yes',
        'roles' => AuthorizationRole::User->value,
        'userCreateConfirmation' => 'yes',
    ];

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('app:user:create');
        $this->commandTester = new CommandTester($command);
    }

    #[DataProvider('getExecuteValidInputDataProvider')]
    public function testExecute(bool $isUserCreationExpected, array $inputData): void
    {
        $this->skipOnWindows();

        $userRepository = static::getContainer()->get(UserRepository::class);
        self::assertEmpty($userRepository->findAll());

        $this->commandTester->setInputs($inputData);

        // Avoid line wrapping in terminal.
        putenv('COLUMNS=' . SymfonyStyle::MAX_LINE_LENGTH);

        $statusCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $statusCode);

        // Ensure passwords are not displayed.
        $this->assertStringNotContainsString($inputData['password'], $output);
        $this->assertStringNotContainsString($inputData['passwordRepeated'], $output);

        // Normalize output to remove indentations and line wrappings.
        $normalizedOutput = preg_replace('/\s+/', ' ', $output);

        $this->assertStringContainsString("Email {$inputData['email']}", $normalizedOutput);
        $this->assertStringContainsString("Agree to terms {$inputData['agreeTerms']}", $normalizedOutput);
        $this->assertStringContainsString("Roles {$inputData['roles']}", $normalizedOutput);

        $this->assertStringContainsString(
            $isUserCreationExpected ? 'The user has been created.' : 'Operation canceled.',
            $output,
        );

        self::assertCount((int) $isUserCreationExpected, $userRepository->findAll());
    }

    public static function getExecuteValidInputDataProvider(): \Generator
    {
        yield 'Happy path' => [
            'isUserCreationExpected' => true,
            'inputData' => self::HAPPY_PATH_INPUT_DATA,
        ];

        $inputData01 = self::HAPPY_PATH_INPUT_DATA;
        $inputData01['roles'] = implode(', ', [AuthorizationRole::Admin->value, AuthorizationRole::User->value]);

        yield 'Happy path with multiple roles' => [
            'isUserCreationExpected' => true,
            'inputData' => $inputData01,
        ];

        $inputData02 = self::HAPPY_PATH_INPUT_DATA;
        $inputData02['userCreateConfirmation'] = 'no';

        yield 'Canceled' => [
            'isUserCreationExpected' => false,
            'inputData' => $inputData02,
        ];
    }

    #[DataProvider('getExecuteInvalidInputDataProvider')]
    public function testExecuteWithInvalidInputsThatAreReenteredWithCorrections(
        array $expectedErrorMessages,
        array $inputData,
    ): void {
        $this->skipOnWindows();

        UserFactory::createOne([
            'email' => 'exists@example.com',
        ]);

        $userManagerMock = $this->prepareUserManagerMock(1);
        static::getContainer()->set(UserManager::class, $userManagerMock);

        $this->commandTester->setInputs($inputData);

        $statusCode = $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $statusCode);

        foreach ($expectedErrorMessages as $errorMessage) {
            $this->assertStringContainsString($errorMessage, $output);
        }
    }

    public static function getExecuteInvalidInputDataProvider(): \Generator
    {
        $notBlankMessage = (new Assert\NotBlank())->message;

        yield 'Invalid email' => [
            'expectedErrorMessages' => [
                $notBlankMessage,
                (new Assert\Email())->message,
                'Unable to register with this email address.',
            ],
            'inputData' => self::getHappyPathDataWithAdditionalInputs([
                'emailEmpty' => '',
                'emailInvalid' => 'invalid',
                'emailAlreadyTaken' => 'exists@example.com',
            ], 'email'),
        ];

        $passwordMinLength = self::getContainer()->getParameter('app.password.min_length');

        yield 'Invalid password' => [
            'expectedErrorMessages' => [
                'Please enter a password.',
                "Your password should be at least {$passwordMinLength} characters long.",
                'Your password should contain at least one digit.',
                'Your password should contain at least one lowercase letter.',
                'Your password should contain at least one uppercase letter.',
                'Your password should contain at least one special character.',
            ],
            'inputData' => self::getHappyPathDataWithAdditionalInputs([
                'passwordEmpty' => '',
                'passwordTooShort' => 'invalid',
                'passwordWithoutDigit' => 'userPassword#',
                'passwordWithoutLowercase' => 'USERPASSWORD#001',
                'passwordWithoutUppercase' => 'userpassword#001',
                'passwordWithoutSpecialCharacters' => 'userPassword001',
            ], 'password'),
        ];

        yield 'Mismatched passwords' => [
            'expectedErrorMessages' => [
                $notBlankMessage,
                'The passwords do not match.',
            ],
            'inputData' => self::getHappyPathDataWithAdditionalInputs([
                'passwordRepeatedEmpty' => '',
                'passwordRepeatedNotMatch' => self::HAPPY_PATH_INPUT_DATA['passwordRepeated'] . 'xyz',
            ], 'passwordRepeated'),
        ];
    }

    /**
     * Useful for entering additional data when you expect to be prompted for data entry again.
     *
     * @param array $additionalData Input data to insert
     * @param string $placeBeforeKey Key of happy path input data key before which additional data is to be entered
     * @return array Non-associative array of input data
     */
    private static function getHappyPathDataWithAdditionalInputs(array $additionalData, string $placeBeforeKey): array
    {
        $data = self::HAPPY_PATH_INPUT_DATA;
        $offset = array_search($placeBeforeKey, array_keys($data));

        array_splice($data, $offset, 0, $additionalData);

        return array_values($data);
    }

    /**
     * @param int $expectedCreateMethodCalls Expected number of create method calls
     */
    private function prepareUserManagerMock(int $expectedCreateMethodCalls): MockObject
    {
        $userManagerMock = $this->createMock(UserManager::class);
        $userManagerMock
            ->expects($this->exactly($expectedCreateMethodCalls))
            ->method('create')
            ->with(
                self::isInstanceOf(UserRegistration::class),
                self::isInstanceOf(User::class),
            );

        return $userManagerMock;
    }

    private function skipOnWindows(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('This test is not supported on Windows.');
        }
    }
}
