<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Event;
use App\Service\QueueHandlerService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RegistrationFixtures extends Fixture implements DependentFixtureInterface
{
    private QueueHandlerService $qhandler;

    public function __construct(QueueHandlerService $qhandler)
    {
        $this->qhandler = $qhandler;
    }

    public function load(ObjectManager $manager): void
    {

        for($i = 0; $i < 20; $i++){ //randomly register each user to 2 events
            $user = $this->getReference("user_$i", User::class);
            $fstEventId = rand(0,4);
            $sndEventId = rand(0,4); //generate 2 random nums, ensure both are different, get event by reference, then add them to q using the handler
            while($sndEventId === $fstEventId){     //which automatically decides if they are in / added to q
                $sndEventId = rand(0,4);
            }
            $fstEvent = $this->getReference("event_$fstEventId", Event::class);
            $sndEvent = $this->getReference("event_$sndEventId", Event::class);
            try {
                $this->qhandler->addToQueue($user, $fstEvent);
                $this->qhandler->addToQueue($user, $sndEvent);
            } catch (\RuntimeException $e) {
                //ignore duplicate exceptions etc just for fixtures
            }
            
        }
    }

    public function getDependencies() : array //user and event fixtures should be loaded first
    {
        return [
            UserFixtures::class,
            EventFixtures::class,
        ];
    }
}
