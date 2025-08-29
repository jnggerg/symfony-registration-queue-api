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
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private ValidatorInterface $validator;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator)
    {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->validator = $validator;
    }

    //expects an email-password combination
    #[Route('/register', name: 'app_register')] 
    public function index(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email']) && isset($data['password'])) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setPlainPassword($data['password']);

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errors = array_map(fn($e) => $e->getMessage(), iterator_to_array($errors));
                return new JsonResponse(['status' => 'error', 'message' => 'Invalid data given', 'errors' => $errors], 400);
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse(['status' => 'success', 'data' => $data], 201);
        }

        return new JsonResponse(['status' => 'error', 'message' => 'Missing email or password'], 400);
    }
}
