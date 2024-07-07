<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\Team;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/task')]
class TaskController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    //private $security;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        //$this->security = $security;
    }

    #[Route('/', name: 'task_index', methods: ['GET'])]
    public function index(Security $security): Response
    {
        $repository=$this->entityManager->getRepository(Task::class);
        $user = $security->getUser();
        
        $tasks = $repository->findAll();
        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/new', name: 'task_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedAt(new \DateTime());
            $task->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id}', name: 'task_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $repository=$this->entityManager->getRepository(Task::class);
        $task = $repository->find($id);

        if (!$task) {
            throw $this->createNotFoundException('The task does not exist');
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    #[Route('/team/{teamId}', name: 'task_by_team')]
    public function getTasksByTeam(int $teamId): Response
    {
        $teamRepository = $this->entityManager->getRepository(Team::class);
        $team = $teamRepository->find($teamId);
        $userRepository = $this->entityManager->getRepository(User::class);
        $taskrepository=$this->entityManager->getRepository(Task::class);
        if (!$team) {
            throw $this->createNotFoundException('The team does not exist');
        }

        $users = $userRepository->findBy(['team' => $team]);
        $tasks = [];

        foreach ($users as $user) {
            $tasks[] = [
                'user' => $user,
                'tasks' => $taskrepository->findBy(['assignee' => $user]),
            ];
        }

        return $this->render('task/team_tasks.html.twig', [
            'team' => $team,
            'tasks' => $tasks,
        ]);
    }

    #[Route('/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task): Response
    {
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($task);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('task_index');
    }

    #[Route('/user/{id}', name: 'user_tasks', methods: ['GET'])]
    public function userTasks(int $id): Response
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->find($id);
        $taskrepository=$this->entityManager->getRepository(Task::class);

        if (!$user) {
            throw $this->createNotFoundException('The user does not exist');
        }

        $tasks = $taskrepository->findBy(['assignee' => $user]);
        $teamTasks = [];

        $team = $user->getTeam();
        if ($team) {
            $teamTasks = $taskrepository->findBy(['team' => $team]);
        }

        return $this->render('task/user_tasks.html.twig', [
            'user' => $user,
            'tasks' => $tasks,
            'teamTasks' => $teamTasks,
        ]);
    }
}
