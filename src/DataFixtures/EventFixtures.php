<?php

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class EventFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for($i = 0; $i < 5; $i++){
            $event = new Event();
            $event->setTitle($faker->unique()->word);
            $event->setDate($faker->dateTimeBetween('+1 week', '+1 month'));
            $event->setCapacity($faker->numberBetween(2, 4));
            $manager->persist($event);
            $this->addReference("event_$i", $event); //reference for registration fixture
        }

        $manager->flush();
    }
}
