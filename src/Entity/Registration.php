<?php
namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_event', columns: ['user_id', 'event_id'])] //every user - event combination must be unique
class Registration
{
   
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['registration:get'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'registeredUsers')]
    #[ORM\JoinColumn(nullable: false)] 
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'registeredEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(nullable: true)]
    private ?int $qposition = null; //if qposition is null, is registered

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getQposition(): ?int
    {
        return $this->qposition;
    }

    public function setQposition(?int $qposition): static
    {
        $this->qposition = $qposition;
        return $this;
    }
}
