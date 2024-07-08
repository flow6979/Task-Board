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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\Length;

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
    public function index(): Response
    {
        $repository = $this->entityManager->getRepository(Task::class);
        // $user = $security->getUser();

        $tasks = $repository->findAll();
        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    // #[Route('/new', name: 'task_new', methods: ['GET', 'POST'])]
    // public function new(Request $request): Response
    // {
    //     $task = new Task();
    //     $form = $this->createForm(TaskType::class, $task);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $task->setCreatedAt(new \DateTime());
    //         $task->setUpdatedAt(new \DateTime());
    //         $this->entityManager->persist($task);
    //         $this->entityManager->flush();

    //         return $this->redirectToRoute('task_index');
    //     }

    //     return $this->render('task/new.html.twig', [
    //         'task' => $task,
    //         'form' => $form->createView(),
    //     ]);
    // }

    #[Route('/new', name: 'task_new', methods: ['POST'])]
    public function new(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['description'], $data['priority'], $data['assignedTo'], $data['deadline'])) {
            return new JsonResponse(['error' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->find($data['assignedTo']);

        if (!$user) {
            return new JsonResponse(['error' => 'Assigned user not found'], Response::HTTP_NOT_FOUND);
        }

        $task = new Task();
        $task->setTitle($data['name']);
        $task->setDescription($data['description']);
        $task->setPriority($data['priority']);
        $task->setAssignee($user);
        $task->setStatus("Not Started");
        $task->setPlannedDate(new \DateTime($data['deadline']));
        $task->setCreatedAt(new \DateTime());
        $task->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $taskDetails = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'priority' => $task->getPriority(),
            'assignedTo' => [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
            ],
            'deadline' => $task->getPlannedDate()->format('Y-m-d'),
            'assignedDate' => $task->getCreatedAt()->format('Y-m-d'),
            'updatedAt' => $task->getUpdatedAt()->format('Y-m-d'),
        ];

        return new JsonResponse($taskDetails, Response::HTTP_CREATED);
    }





    #[Route('/{id}/edit', name: 'task_edit', methods: ['POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager,LoggerInterface $logger): Response
    {
        // Decode JSON from request body
        $data = json_decode($request->getContent(), true);

        // Log the decoded data
        if ($data) {
            $logger->info('Decoded JSON data: ' . json_encode($data));
        } else {
            $logger->error('Failed to decode JSON');
        }

        if (!$task) {
            return new JsonResponse(["error" => "Task not available"], Response::HTTP_NOT_FOUND);
        }
        if (!isset($data['assignedTo']['id'])) {
            return new JsonResponse(["error" => "Assigned user ID is missing"], Response::HTTP_BAD_REQUEST);
        }

        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['id' => $data['assignedTo']['id']]);

        if (!$user) {
            return new JsonResponse(["error" => "User not available"], Response::HTTP_NOT_FOUND);
        }

        $task->setAssignee($user);

        if (isset($data['title'])) {
            $task->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        if (isset($data['priority'])) {
            $task->setPriority($data['priority']);
        }
        if (isset($data['deadline'])) {
            $deadline = new \DateTime($data['deadline']);
            $task->setPlannedDate($deadline);
        }
        if (isset($data['status'])) {
            $task->setStatus($data['status']);
        }

        $task->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return new JsonResponse(['message' => 'Task updated successfully!'], Response::HTTP_OK);
    }



    #[Route('/{id}', name: 'task_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $repository = $this->entityManager->getRepository(Task::class);
        $task = $repository->find($id);

        if (!$task) {
            throw $this->createNotFoundException('The task does not exist');
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }


    #[Route('/team/{teamId}', name: 'task_by_team', methods: ["GET"])]
    public function getTasksByTeam(int $teamId): JsonResponse
    {
        $teamRepository = $this->entityManager->getRepository(Team::class);
        $team = $teamRepository->find($teamId);

        if (!$team) {
            throw $this->createNotFoundException('The team does not exist');
        }

        $userRepository = $this->entityManager->getRepository(User::class);
        $taskRepository = $this->entityManager->getRepository(Task::class);

        $users = $userRepository->findBy(['team' => $team]);
        $tasks = [];

        foreach ($users as $user) {
            $userTasks = $taskRepository->findBy(['assignee' => $user]);

            foreach ($userTasks as $userTask) {
                $tasks[] = [
                    'id' => $userTask->getId(),
                    'title' => $userTask->getTitle(),
                    'description' => $userTask->getDescription(),
                    'priority' => $userTask->getPriority(),
                    'status' => $userTask->getStatus(),
                    'assignedTo' => [
                        'id' => $user->getId(),
                        'name' => $user->getFullName(),
                        'email' => $user->getEmail(),
                    ],
                    'deadline' => $userTask->getPlannedDate()->format('Y-m-d'),
                    'assignedDate' => $userTask->getCreatedAt()->format('Y-m-d'),
                    'updatedt' => $userTask->getUpdatedAt()->format('Y-m-d'),
                ];
            }
        }

        return new JsonResponse($tasks, Response::HTTP_OK);
    }


    #[Route('/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task): JsonResponse
    {
        // if ($this->isCsrfTokenValid('delete' . $task->getId(), $request->request->get('_token'))) {
        $this->entityManager->remove($task);
        $this->entityManager->flush();
        // }
        return new JsonResponse(["messgae" => "task deleted successfully"]);
        // return $this->redirectToRoute('task_index');
    }

    #[Route('/user/{id}', name: 'user_tasks', methods: ['GET'])]
    public function userTasks(int $id): Response
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->find($id);
        $taskrepository = $this->entityManager->getRepository(Task::class);

        if (!$user) {
            throw $this->createNotFoundException('The user does not exist');
        }

        $tasks = $taskrepository->findBy(['assignee' => $user]);
        $teamTasks = [];
        
        if($user->getTeam()){
            $teamembers = $userRepository -> findBy(['team' => $user->getTeam()]);
            forEach($teamembers as $mem){
                $memberTasks = $taskrepository->findBy(["assignee" => $mem]);
                foreach ($memberTasks as $userTask) {
                    $teamTasks[] = [
                        'id' => $userTask->getId(),
                        'title' => $userTask->getTitle(),
                        'description' => $userTask->getDescription(),
                        'priority' => $userTask->getPriority(),
                        'status' => $userTask->getStatus(),
                        'assignedTo' => [
                            'id' => $mem->getId(),
                            'name' => $mem->getFullName(),
                            'email' => $mem->getEmail(),
                        ],
                        'deadline' => $userTask->getPlannedDate()->format('Y-m-d'),
                        'assignedDate' => $userTask->getCreatedAt()->format('Y-m-d'),
                        'updatedt' => $userTask->getUpdatedAt()->format('Y-m-d'),
                    ];
                }
                
            }
        }
        else{

            $tasks = $taskrepository->findBy(["assignee" => $user]);
            foreach ($tasks as $task) {
                $teamTasks[] = [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'priority' => $task->getPriority(),
                    'status' => $task->getStatus(),
                    'assignedTo' => [
                        'id' => $user->getId(),
                        'name' => $user->getFullName(),
                        'email' => $user->getEmail(),
                    ],
                    'deadline' => $task->getPlannedDate()->format('Y-m-d'),
                    'assignedDate' => $task->getCreatedAt()->format('Y-m-d'),
                    'updatedt' => $task->getUpdatedAt()->format('Y-m-d'),
                ];
            }

        }

        return new JsonResponse(["teamTasks" => $teamTasks]);
    }
}
