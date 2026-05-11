<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

class CreateBoardService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function createForProject(Project $project): Board
    {
        $board = (new Board())
            ->setProject($project)
            ->setName('Tablero Principal');

        foreach ($this->defaultColumns() as $columnData) {
            $column = (new BoardColumn())
                ->setBoard($board)
                ->setName($columnData['name'])
                ->setPosition($columnData['position'])
                ->setColor($columnData['color']);

            $this->em->persist($column);
        }

        $this->em->persist($board);

        return $board;
    }

    /** @return array<int, array{name: string, position: int, color: string}> */
    private function defaultColumns(): array
    {
        return [
            ['name' => 'Por hacer', 'position' => 1, 'color' => '#E2E8F0'],
            ['name' => 'En progreso', 'position' => 2, 'color' => '#FDE68A'],
            ['name' => 'En revisión', 'position' => 3, 'color' => '#BFDBFE'],
            ['name' => 'Hecho', 'position' => 4, 'color' => '#BBF7D0'],
        ];
    }
}
