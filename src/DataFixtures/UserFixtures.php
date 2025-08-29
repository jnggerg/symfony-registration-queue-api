<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHash;

    public function __construct(UserPasswordHasherInterface $passwordHash)
    {
        $this->passwordHash = $passwordHash;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $admin_user = new User();
        $admin_user->setEmail("admin@admin.com");
        $admin_user->setPassword($this->passwordHash->hashPassword($admin_user, "admin123asd"));
        $admin_user->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin_user);

        for($i = 0; $i < 20; $i++){ //generate 20 users for testing, each will have 2 registrations
            $user = new User();
            $user->setEmail($faker->unique()->email);
            $user->setPassword($this->passwordHash->hashPassword($user, "test123asd")); //all passwords are same for testing
            $manager->persist($user);
            $this->addReference("user_$i", $user); //reference for registration fixture
        }

        $manager->flush();
    }
}
