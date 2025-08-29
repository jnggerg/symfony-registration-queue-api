<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    //expects an email-password combination
    #[Route('/register', name: 'app_register')] 
    public function index(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email']) && isset($data['password'])) {
            if(!is_string($data['email']) || !is_string($data['password'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Invalid email or password'], 400);
            }
            if (strlen($data['password']) < 6) {
                return new JsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters'], 400);
            }
            
            if ($this->userRepository->findOneBy(['email' => $data['email']])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Email already registered'], 400);
            }
            $user = new User();
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse(['status' => 'success', 'data' => $data], 201);
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Missing email or password'], 400);
    }
}
