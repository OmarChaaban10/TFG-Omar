<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GlobalRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(enumType: GlobalRole::class, options: ['default' => 'member'])]
    private GlobalRole $globalRole = GlobalRole::MEMBER;

    #[ORM\Column(name: 'avatar_url', type: 'text', nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Project::class)]
    private Collection $ownedProjects;

    /** @var Collection<int, ProjectMember> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ProjectMember::class, orphanRemoval: true)]
    private Collection $projectMemberships;

    /** @var Collection<int, Card> */
    #[ORM\OneToMany(mappedBy: 'assignee', targetEntity: Card::class)]
    private Collection $assignedCards;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->ownedProjects = new ArrayCollection();
        $this->projectMemberships = new ArrayCollection();
        $this->assignedCards = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return array_values(array_unique([
            'ROLE_USER',
            $this->globalRole->toSecurityRole(),
        ]));
    }

    public function getGlobalRole(): GlobalRole
    {
        return $this->globalRole;
    }

    public function setGlobalRole(GlobalRole $globalRole): self
    {
        $this->globalRole = $globalRole;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /** @return Collection<int, Project> */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    public function addOwnedProject(Project $project): self
    {
        if (!$this->ownedProjects->contains($project)) {
            $this->ownedProjects->add($project);
            $project->setOwner($this);
        }

        return $this;
    }

    public function removeOwnedProject(Project $project): self
    {
        if ($this->ownedProjects->removeElement($project) && $project->getOwner() === $this) {
            $project->setOwner(null);
        }

        return $this;
    }

    /** @return Collection<int, ProjectMember> */
    public function getProjectMemberships(): Collection
    {
        return $this->projectMemberships;
    }

    public function addProjectMembership(ProjectMember $projectMember): self
    {
        if (!$this->projectMemberships->contains($projectMember)) {
            $this->projectMemberships->add($projectMember);
            $projectMember->setUser($this);
        }

        return $this;
    }

    public function removeProjectMembership(ProjectMember $projectMember): self
    {
        if ($this->projectMemberships->removeElement($projectMember) && $projectMember->getUser() === $this) {
            $projectMember->setUser(null);
        }

        return $this;
    }

    /** @return Collection<int, Card> */
    public function getAssignedCards(): Collection
    {
        return $this->assignedCards;
    }

    public function addAssignedCard(Card $card): self
    {
        if (!$this->assignedCards->contains($card)) {
            $this->assignedCards->add($card);
            $card->setAssignee($this);
        }

        return $this;
    }

    public function removeAssignedCard(Card $card): self
    {
        if ($this->assignedCards->removeElement($card) && $card->getAssignee() === $this) {
            $card->setAssignee(null);
        }

        return $this;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification) && $notification->getUser() === $this) {
            $notification->setUser(null);
        }

        return $this;
    }
}
