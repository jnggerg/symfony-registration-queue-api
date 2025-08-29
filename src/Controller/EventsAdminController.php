<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\QueueHandlerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use DateTime;
use DateTimeZone;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class EventsAdminController extends AbstractController{
    private EventRepository $eventRepository;
    private RegistrationRepository $registrationRepository;
    private UserRepository $userRepository;
    private QueueHandlerService $qhandler;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        EventRepository $eventRepository,
        RegistrationRepository $registrationRepository,
        UserRepository $userRepository,
        QueueHandlerService $qhandler,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->eventRepository = $eventRepository;
        $this->registrationRepository = $registrationRepository;
        $this->userRepository = $userRepository;
        $this->qhandler = $qhandler;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    //used in register/unregister user - cannot use validator since entity has other entities as fields not "ids",
    // adding seperate "id" fields that wouldnt be added to db would be just as messy since its only used here
    public function validateRegisterRequests($req): string{
        if(!$req){
            return 'Invalid JSON / No content given';
        }
        if(!isset($req['user_id']) || !isset($req['event_id'])){
            return 'Missing parameters user_id or event_id data';
        }
        if(!ctype_digit($req["event_id"]) || !ctype_digit(trim($req["user_id"]))){ 
            return 'Both IDs must be valid integers';
        }
        return 'OK';
    }

    //used in create/delete event
    public function validateEventAttributes($req, bool $create): string{
        //handling the case of create
        if($create){
            $event = new Event();
            if(!isset($req['title']) || !isset($req['date']) || !isset($req['capacity'])){
                return 'Missing parameters title, date or capacity';
            } //try-catch wont catch this since it would only return a warning
            try{ //checking conversion errors
                $title = trim($req['title']);
                $date = new DateTime($req['date']); //cant call trim on it since it messes up format
                $capacity = (int) trim($req['capacity']);
            }catch(\Exception $e){
                return 'Invalid data format: ' . $e->getMessage();
            }
            $event->setTitle($title);
            $event->setDate($date);
            $event->setCapacity($capacity);

            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                $errors = array_map(fn($e) => $e->getMessage(), iterator_to_array($errors));
                return 'Invalid event data given: ' . implode(', ', $errors);
            }

            return "OK";
        } 
        //handling the case of edit -> cannot handle with validator due to NotBlank statements
        if (!isset($req['event_id'])) {
            return 'Missing parameter event_id';
        }
        $validationEvent = new Event();
        $validationEvent->setTitle("default title for testing");
        $validationEvent->setDate(new DateTime("2099-12-31 23:59:59"));
        $validationEvent->setCapacity(1);
        try{ 
            if (isset($req['title'])) {
                $validationEvent->setTitle(trim($req['title']));
            }
            if (isset($req['date'])) {
                $validationEvent->setDate(new DateTime($req['date']));
            }
            if (isset($req['capacity'])) {
                $validationEvent->setCapacity((int)$req['capacity']);
            }
        }catch(\Exception $e){
            return 'Invalid data format: ' . $e->getMessage();
        }
        $errors = $this->validator->validate($validationEvent);
        if (count($errors) > 0) {
            $fields = array_map(fn($e) => $e->getPropertyPath(), iterator_to_array($errors));
            $errors = array_map(fn($e) => $e->getMessage(), iterator_to_array($errors));
            return 'Invalid event data given: ' . implode(', ', $errors) . ' in fields: ' . implode(', ', $fields);
        }
        return "OK";
    }


    //unregister a user from event, expects: event_id, user_id
    #[Route('/events/admin/unregister', name: 'app_admin_events_unregister', methods: ['POST'])]
    public function unregisterUser(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        $val = $this->validateRegisterRequests($request_data);
        if ($val !== "OK") {
            return new JsonResponse(['status' => 'error', 'message' => $val], 400);
        }

        $user_id = (int) $request_data['user_id'];
        $event_id = (int) $request_data['event_id'];

        $registration = $this->registrationRepository->findByUserEventId($user_id, $event_id);
        if (!$registration) {
            return new JsonResponse(['status' => 'error', 'message' => 'Registration not found'], 404);
        }
        try{
            $this->qhandler->removeFromQueue($registration->getUser(), $registration->getEvent());
            return new JsonResponse(['status' => "success", 'message' => 'Successfully unregistered from event'], 200);
        }catch(\RuntimeException $e){
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    //register a user to an event (against their will), expects: event_id, user_id
    #[Route('/events/admin/register', name: 'app_admin_events_register', methods: ['POST'])]
    public function registerUser(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        $val = $this->validateRegisterRequests($request_data);
        if ($val !== "OK") {
            return new JsonResponse(['status' => 'error', 'message' => $val], 400);
        }

        $user_id = (int) $request_data['user_id'];
        $event_id = (int) $request_data['event_id'];

        $registration = $this->registrationRepository->findByUserEventId($user_id, $event_id);
        if ($registration !== null) {
            return new JsonResponse(['status' => 'error', 'message' => 'User already registered for this event'], 400);
        }
        $user = $this->userRepository->find($user_id);
        $event = $this->eventRepository->find($event_id);
        if (!$user || !$event) {
            return new JsonResponse(['status' => 'error', 'message' => 'User or Event not found for these IDs'], 400);
        }

        try{
            $qposition = $this->qhandler->addToQueue($user, $event);
            if ($qposition === null){
                return new JsonResponse(['status' => 'success', 'message' => 'Successfully registered for event'], 200);
            }
            return new JsonResponse(['status' => 'success', 'message' => 'Added to queue', 'qposition' => $qposition], 200);
        }catch(\RuntimeException $e){
            print("Error: " . $e->getMessage());
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    //create event expects: title, date, capacity
    #[Route('/events/admin/create', name: 'app_admin_events_create', methods: ['POST'])]
    public function createEvent(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        $val = $this->validateEventAttributes($request_data, true);
        if ($val !== "OK") {
            return new JsonResponse(['status' => 'error', 'message' => $val], 400);
        }
        $event = new Event();
        $event->setTitle($request_data['title']);
        $event->setCapacity((int)$request_data['capacity']);
        $event->setDate(new DateTime($request_data['date'], new DateTimeZone("Europe/Budapest")));

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Event created successfully'], 201);
    }

    //delete event expects: event_id 
    #[Route('events/admin/delete', name: 'app_admin_events_delete', methods: ['DELETE'])]
    public function deleteEvent(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        if (!isset($request_data['event_id'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing parameter event_id'], 400);
        }
        $event_id = (string) $request_data['event_id'];
        if (!ctype_digit($event_id)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid event_id: must be a positive integer'], 400);
        }

        $event = $this->eventRepository->find((int)$event_id);
        if (!$event) {
            return new JsonResponse(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Event deleted successfully'], 200);
    }

    //edit event expects: event_id, ?title, ?date, ?capacity
    #[Route('events/admin/edit', name: 'app_admin_events_edit', methods: ['PUT'])]
    public function editEvent(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        $val = $this->validateEventAttributes($request_data, false);
        if ($val !== "OK") {
            return new JsonResponse(['status' => 'error', 'message' => $val], 400);
        }

        $event_id = $request_data['event_id'];
        $event = $this->eventRepository->find($event_id);
        $changes = 0;
        if (!$event) {
            return new JsonResponse(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        if (isset($request_data['title'])) {
            $event->setTitle($request_data['title']);
            $changes++;
        }
        if (isset($request_data['date'])) {
            $date = new DateTime($request_data['date'], new DateTimeZone('Europe/Budapest'));
            $event->setDate($date);
            $changes++;
        }
        if (isset($request_data['capacity'])) {
            $capacity = $request_data['capacity'];
            $event->setCapacity((int)$capacity);
            $changes++;
        }

        $this->entityManager->flush();
        if ($changes === 0) {
            return new JsonResponse(['status' => 'success', 'message' => 'No changes made'], 200);
        }
        return new JsonResponse(['status' => 'success', 'message' => 'Event updated successfully, changes made: ' . $changes], 200);
    }

}
