<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use DateTime;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event:get'])]
    private ?int $id = null;

    #[Groups(['event:get'])]
    #[ORM\Column(type: Types::TEXT, length: 150)]
    #[Assert\Length(min: 3, max: 150)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\NotBlank]
    #[Groups(['event:get'])]
    private ?int $capacity = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event:get'])]
    //#[Assert\DateTime] not needed since it only checks if given string can be converted to a datetime
    #[Assert\GreaterThan('now')]
    #[Assert\NotBlank]
    private DateTime $date;

    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'event', orphanRemoval: true)] //using mapped by since relation is bidirectional
    private Collection $registrations;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();

    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addToRegistrations(Registration $registration): static
    {
        $this->registrations[] = $registration;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getRegisteredUserCount(): int
    {
        return $this->registrations->filter(fn($registration) => $registration->getQposition() === null)->count();
    }
    
    public function getQueueLength(): int
    {
        return $this->registrations->count() - $this->getRegisteredUserCount();
    }

    public function getUsersPositionInQueue(User $user): ?int
    {
        return $this->registrations->indexOf($user);
    }
}
