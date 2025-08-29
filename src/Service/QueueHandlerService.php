<?php

namespace App\Service;

//this class will handle all the interactions of adding, removing and shifting queue
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\Registration;
use App\Repository\RegistrationRepository;

class QueueHandlerService{
    private EntityManagerInterface $entityManager;
    private RegistrationRepository $registrationRepository;

    public function __construct(EntityManagerInterface $entityManager, RegistrationRepository $registrationRepository)
    {
        $this->entityManager = $entityManager;
        $this->registrationRepository = $registrationRepository;
    }

    public function addToQueue(User $user, Event $event){
        if($this->registrationRepository->findByEventUser($user, $event)){
            throw new \RuntimeException("User already registered for this event");
        }
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setEvent($event);
        if($event->getCapacity() - $event->getRegisteredUserCount() <= 0){                      //check if no capacity left, set qposition to max + 1
            $registration->setQposition($this->registrationRepository->findByEventMaxQpositionInEvent($event) + 1); // if there is space left, user will have a default
        }                                                                                       //qposition value of null, which means registered
        $event->addToRegistrations($registration);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();
        return $registration->getQposition(); //return qposition, null if user is registered, int(x) > 0 if in queue
    }

    public function removeFromQueue(User $user, Event $event)
    {
        $registration = $this->registrationRepository->findByEventUser($user, $event);
        if ($registration == null) {
            throw new \RuntimeException("Registration not found for these user and event entities");
        }

        $this->entityManager->remove($registration);
        $this->updateQPostitions($registration->getQposition(), $event); //updating rest of the q based in one event on removed reg qposition
        $this->entityManager->flush(); //only need to flush after all q changes made
    }

    public function updateQPostitions(?int $qpos, Event $event): void{
        if ($qpos === null){ //handle fully registered user case, push 1st in q into registered
            $firstInQueue = $this->registrationRepository->findByEventFirstInQueue($event);
            if($firstInQueue !== null){ 
                $firstInQueue->setQposition(null);
                $this->entityManager->persist($firstInQueue);
                $qpos = 1; //for shifting rest of the qpositions
            }
            else{ //if firstInQueue is null, no users are in queue, nothing more to do
                return; 
            }
        }

        $registrations = $this->registrationRepository->findByEventRestOfQueue($event, $qpos);
        foreach ($registrations as $reg){
            $reg->setQposition($reg->getQposition() - 1);
            $this->entityManager->persist($reg);
        }
    }


}