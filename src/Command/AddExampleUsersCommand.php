<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;

#[AsCommand(
    name: 'app:add-example-users',
    description: 'Adds example admin and user to the database',
)]
class AddExampleUsersCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create admin user
        $admin = new User();
        $admin->setEmail('adm@example.com');
        $admin->setRoles(['ROLE_ADMIN']);

        $adminPassword = $this->passwordHasher->hashPassword($admin, 'admin_password');
        $admin->setPassword($adminPassword);

        // Create regular user
        $user = new User();
        $user->setEmail('wolfrobin858@gmail.com');
        $user->setRoles(['ROLE_USER']);

        $userPassword = $this->passwordHasher->hashPassword($user, 'user_password');
        $user->setPassword($userPassword);

        // Persist users
        $this->entityManager->persist($admin);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Example admin and user have been added to the database.');

        return Command::SUCCESS;
    }
}
