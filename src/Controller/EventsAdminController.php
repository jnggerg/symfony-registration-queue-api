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

#[IsGranted('ROLE_ADMIN')]
final class EventsAdminController extends AbstractController{
    private EventRepository $eventRepository;
    private RegistrationRepository $registrationRepository;
    private UserRepository $userRepository;
    private QueueHandlerService $qhandler;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EventRepository $eventRepository,
        RegistrationRepository $registrationRepository,
        UserRepository $userRepository,
        QueueHandlerService $qhandler,
        EntityManagerInterface $entityManager
    ) {
        $this->eventRepository = $eventRepository;
        $this->registrationRepository = $registrationRepository;
        $this->userRepository = $userRepository;
        $this->qhandler = $qhandler;
        $this->entityManager = $entityManager;
    }

    //used in register/unregister user
    public function validateRegisterRequests($req): string{
        if(!$req){
            return 'Invalid JSON / No content given';
        }
        if(!isset($req['user_id']) || !isset($req['event_id'])){
            return 'Missing parameters user_id or event_id data';
        }
        if(!ctype_digit($req["event_id"]) || !ctype_digit($req["user_id"])){
            return 'Both IDs must be valid integers';
        }
        return 'OK';
    }

    //used in create/delete event
    public function validateEventAttributes($req, bool $create): string{
        //handling the case of create
        if($create){
            if(!isset($req['title']) || !isset($req['date']) || !isset($req['capacity'])){
                return 'Missing parameters title, date or capacity';
            }
            if(!is_string($req['title']) || trim($req['title']) === '' || strlen($req['title']) > 150){
                return 'Invalid title: must be a non-empty string and less than 150 characters';
            }
            if(!DateTime::createFromFormat('Y-m-d H:i:s', $req['date'])){ //returns false if date is invalid
                return 'Invalid date format: must be Y-m-d H:i:s';
            }
            $now = new DateTime('now', new DateTimeZone('Europe/Budapest'));
            if($now > DateTime::createFromFormat('Y-m-d H:i:s', $req['date'], new DateTimeZone('Europe/Budapest'))){
                return 'Invalid date: date can not be in the past';
            }
            $capacity = $req['capacity'];
            if(!ctype_digit($capacity) || (int)$capacity < 0){
                return 'Invalid capacity: must be a positive integer';
            }
            return "OK";
        } 
        //handling the case of edit
        if (!isset($req['event_id'])) {
            return 'Missing parameter event_id';
        }
        if (isset($req['title'])) {
            if (!is_string($req['title']) || trim($req['title']) === '' || strlen($req['title']) > 150) {
                return 'Invalid title: must be a non-empty string and less than 150 characters';
            }
        }
        if (isset($req['date'])) {
            if(!DateTime::createFromFormat('Y-m-d H:i:s', $req['date'])){ //returns false if date is invalid
                return 'Invalid date format: must be Y-m-d H:i:s';
            }
            $now = new DateTime('now', new DateTimeZone('Europe/Budapest'));
            if($now > DateTime::createFromFormat('Y-m-d H:i:s', $req['date'], new DateTimeZone('Europe/Budapest'))){
                return 'Invalid date: date can not be in the past';
            }
        }
        if (isset($req['capacity'])) {
            $capacity = $req['capacity'];
            if(!ctype_digit($capacity) || (int)$capacity < 0){
                return 'Invalid capacity: must be a positive integer';
            }
        }
        return "OK";
    }


    //unregister a user from event
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

    //register a user to an event (against their will)
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

    //create event
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

    //delete event
    #[Route('events/admin/delete', name: 'app_admin_events_delete', methods: ['DELETE'])]
    public function deleteEvent(Request $request): JsonResponse
    {
        $request_data = json_decode($request->getContent(), true);
        if (!isset($request_data['event_id'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing parameter event_id'], 400);
        }
        $event_id = $request_data['event_id'];
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

    //edit event
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
