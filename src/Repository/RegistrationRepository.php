<?php

namespace App\Repository;

use App\Entity\Registration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Event;
use App\Entity\User;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    //returns the highest qposition for given event
    public function findByEventMaxQpositionInEvent(Event $event): ?int
    {
        return $this->createQueryBuilder('r')
            ->select('MAX(r.qposition)')
            ->where('r.event = :event')
            ->setParameter('event', $event)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //just for cleaner code in queuehandler
    public function findByEventUser(User $user, Event $event): ?Registration{
        return $this->findOneBy(['user' => $user, 'event' => $event]);
    }

    //all registrations for a user by id
    public function findByUserId(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    //for checking if user already registered by ids
    public function findByUserEventId(int $user_id, int $event_id): ?Registration
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user_id')
            ->andWhere('r.event = :event_id')
            ->setParameter('user_id', $user_id)
            ->setParameter('event_id', $event_id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //returns the first 1st user in the queue for the event given in argument
    public function findByEventFirstInQueue(Event $event): ?Registration
    {
        return $this->createQueryBuilder('r')
            ->where('r.event = :event')
            ->andWhere('r.qposition = 1')
            ->setParameter('event', $event)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //query all registrations in event with greater qposition then given in argument
    public function findByEventRestOfQueue(Event $event, int $qpos): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.event = :event')
            ->andWhere('r.qposition > :qpos') //null automatically not taken into account
            ->setParameter('event', $event)
            ->setParameter('qpos', $qpos)
            ->getQuery()
            ->getResult();
    }
}
