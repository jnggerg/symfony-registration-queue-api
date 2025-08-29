<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\QueueHandlerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[IsGranted('ROLE_USER')]
final class EventsRestController extends AbstractController
{
    private EventRepository $eventRepository;
    private RegistrationRepository $registrationRepository;
    private QueueHandlerService $qhandler;
    private SerializerInterface $serializer;

    public function __construct(EventRepository $eventRepository, RegistrationRepository $registrationRepository, QueueHandlerService $qhandler, SerializerInterface $serializer)
    {
        $this->eventRepository = $eventRepository;
        $this->registrationRepository = $registrationRepository;
        $this->qhandler = $qhandler;
        $this->serializer = $serializer;
    }

    public function getCurrentUser(): User
    {
         /** @var \App\Entity\User $current_user */
        $current_user = $this->getUser(); //type-hinting so IDE wont push error for UserInterface
        if (!$current_user) {
            throw $this->createAccessDeniedException();
        }
        return $current_user;
    }

    //get all events
    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function all_events(): JsonResponse
    {
        $all_events = $this->eventRepository->findAll();
        if(!$all_events) {
            return new JsonResponse(['status' => 'error', 'message'=> "No events found"], 404);
        }

        //need serializer to encode Event into json properly, with groups to prevent circular reference
        //this way it returns every field except registrations, which has seperate endpoint
        $all_events_json = $this->serializer->serialize($all_events, 'json', ['groups' => ['event:get']]);
        return new JsonResponse($all_events_json, 200, [], true);
    }

    //get a single event by id passed
    #[Route('/events/{id}', name:'app_event_id', methods: ['GET'])]
    public function event(string $id): JsonResponse
    {
        if (!ctype_digit($id)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid event ID'], 400);
        }
        $id = (int) $id;
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return new JsonResponse(['status' => 'error', 'message'=> "Event not found"], 404);
        }

        $event_json = $this->serializer->serialize($event, 'json', ['groups' => ['event:get']]);
        return new JsonResponse($event_json, 200, [], true);
    }

    //get only the events the logged in user is registered to
    #[Route('/my_events', name: 'app_my_events', methods: ['GET'])]
    public function my_events(): JsonResponse
    {
        $registrations = $this->registrationRepository->findByUserId($this->getCurrentUser()->getId());
        if(!$registrations) {
            return new JsonResponse(['status' => 'error', 'message'=> "No registrations found"], 404);
        }
        $my_events = array_map(fn($reg) => $reg->getEvent(), $registrations);
        $my_events_json = $this->serializer->serialize($my_events, 'json', ['groups' => ['event:get']]);
        return new JsonResponse($my_events_json, 200, [], true);
    }

    //unregister from event
    #[Route('/events/unregister/{event_id}', name: 'app_events_unregister', methods: ['POST'])]
    public function unregister(string $event_id): JsonResponse
    {
        if (!ctype_digit($event_id)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid event ID'], 400);
        }
        $event_id = (int) $event_id;

        $registration = $this->registrationRepository->findByUserEventId($this->getCurrentUser()->getId(), $event_id);
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

    //register to an event
    #[Route('/events/register/{event_id}', name: 'app_events_register', methods: ['POST'])]
    public function register(string $event_id): JsonResponse
    {
        if (!ctype_digit($event_id)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid event ID'], 400);
        }
        $event_id = (int) $event_id;
        $event = $this->eventRepository->find($event_id);
        if (!$event) {
            return new JsonResponse(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        try{
            $qposition = $this->qhandler->addToQueue($this->getCurrentUser(), $event);
            if ($qposition === null){
                return new JsonResponse(['status' => 'success', 'message' => 'Successfully registered for event'], 200);
            }
            return new JsonResponse(['status' => 'success', 'message' => 'Added to queue', 'qposition' => $qposition], 200);
        }catch(\RuntimeException $e){
            print("Error: " . $e->getMessage());
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

}
