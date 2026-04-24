<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CardPriority;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cards')]
#[ORM\Index(name: 'idx_card_assignee_column', columns: ['assignee_id', 'column_id'])]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BoardColumn::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(name: 'column_id', nullable: false, onDelete: 'CASCADE')]
    private ?BoardColumn $column = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedCards')]
    #[ORM\JoinColumn(name: 'assignee_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: CardPriority::class, options: ['default' => 'medium'])]
    private CardPriority $priority = CardPriority::MEDIUM;

    #[ORM\Column(name: 'due_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(mappedBy: 'card', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    /** @var Collection<int, Label> */
    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'cards')]
    #[ORM\JoinTable(name: 'card_labels')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'label_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $labels;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->labels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColumn(): ?BoardColumn
    {
        return $this->column;
    }

    public function setColumn(?BoardColumn $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPriority(): CardPriority
    {
        return $this->priority;
    }

    public function setPriority(CardPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

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
            $notification->setCard($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification) && $notification->getCard() === $this) {
            $notification->setCard(null);
        }

        return $this;
    }

    /** @return Collection<int, Label> */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
            $label->addCard($this);
        }

        return $this;
    }

    public function removeLabel(Label $label): self
    {
        if ($this->labels->removeElement($label)) {
            $label->removeCard($this);
        }

        return $this;
    }
}
