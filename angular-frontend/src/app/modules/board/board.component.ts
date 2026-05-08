import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';
import { DragDropModule, CdkDragDrop } from '@angular/cdk/drag-drop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ThemeToggleComponent } from '../shared/theme-toggle/theme-toggle.component';

export interface Label {
  id: number;
  name: string;
  color: string;
}

export interface Assignee {
  id: number;
  name: string;
  avatarUrl: string | null;
}

export interface Card {
  id: number;
  title: string;
  description: string | null;
  priority: string | null;
  position: number;
  dueDate: string | null;
  assignee: Assignee | null;
  labels: Label[];
}

export interface BoardColumn {
  id: number;
  name: string;
  color: string;
  position: number;
  cards: Card[];
}

export interface Board {
  id: number;
  name: string;
  columns: BoardColumn[];
}

interface BoardResponse {
  board: Board;
  currentUserId: number;
}

type BoardFilter = 'highPriority' | 'myTasks' | 'thisWeek';

@Component({
  selector: 'app-board',
  standalone: true,
  imports: [CommonModule, DragDropModule, FormsModule, ThemeToggleComponent],
  templateUrl: './board.component.html'
})
export class BoardComponent implements OnInit {
  projectId = 0;
  projectName = '';
  board: Board | null = null;
  currentUserId: number | null = null;
  isLoading = false;
  error = '';
  searchQuery = '';
  activeFilters: Record<BoardFilter, boolean> = {
    highPriority: false,
    myTasks: false,
    thisWeek: false,
  };
  
  // Para los id de las listas conectadas de drag & drop
  connectedLists: string[] = [];

  private readonly http = inject(HttpClient);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private loadedProjectId: number | null = null;

  ngOnInit(): void {
    this.route.paramMap
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        const routeProjectId = Number(params.get('id'));

        if (Number.isInteger(routeProjectId) && routeProjectId > 0) {
          const projectName = this.route.snapshot.queryParamMap.get('name') ?? params.get('name') ?? '';
          this.loadProject(routeProjectId, projectName);
          return;
        }

        this.error = 'Proyecto no válido.';
      });
  }

  get boardTitle(): string {
    return this.projectName || this.board?.name || 'Tablero Kanban';
  }

  private loadProject(projectId: number, projectName: string): void {
    if (!Number.isInteger(projectId) || projectId <= 0) {
      this.error = 'Proyecto no válido.';
      this.board = null;
      return;
    }

    if (this.loadedProjectId !== projectId) {
      this.board = null;
      this.searchQuery = '';
    }

    this.projectId = projectId;
    this.projectName = projectName || this.projectName;
    this.loadedProjectId = projectId;
    this.fetchBoard();
  }

  fetchBoard(): void {
    this.isLoading = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<BoardResponse>(`/api/projects/${this.projectId}/board`, {
      headers: { Authorization: `Bearer ${token}` }
    })
      .pipe(
        finalize(() => { this.isLoading = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.board = res.board;
          this.currentUserId = res.currentUserId;
          if (!this.projectName) {
            this.projectName = res.board.name;
          }
          // Actualizamos los IDs de conexión (col-1, col-2...)
          if (this.board && this.board.columns) {
            this.connectedLists = this.board.columns.map(c => `col-${c.id}`);
          }
        },
        error: (err) => {
          this.error = err.error?.message || 'Error al cargar el tablero.';
        }
      });
  }

  backToDashboard(): void {
    this.router.navigate(['/dashboard']);
  }

  logout(): void {
    localStorage.removeItem('jwt_token');
    sessionStorage.removeItem('jwt_token');
    this.router.navigate(['/']);
  }

  drop(event: CdkDragDrop<Card[]>): void {
    if (!this.board) return;

    const cardId = event.item.data.id;
    const sourceColumn = this.findColumnByContainerId(event.previousContainer.id);
    const targetColumn = this.findColumnByContainerId(event.container.id);

    if (!sourceColumn || !targetColumn) return;

    const sourceIndex = sourceColumn.cards.findIndex(card => card.id === cardId);
    if (sourceIndex === -1) return;

    const [movedCard] = sourceColumn.cards.splice(sourceIndex, 1);
    const targetIndex = this.getTargetInsertIndex(targetColumn, cardId, event.currentIndex);
    targetColumn.cards.splice(targetIndex, 0, movedCard);

    this.reindexColumn(sourceColumn);
    if (sourceColumn !== targetColumn) {
      this.reindexColumn(targetColumn);
    }

    this.persistCardMove(event.container.id, cardId, targetIndex);
  }

  private findColumnByContainerId(containerId: string): BoardColumn | undefined {
    const columnId = parseInt(containerId.replace('col-', ''), 10);
    return this.board?.columns.find(column => column.id === columnId);
  }

  private getTargetInsertIndex(targetColumn: BoardColumn, movedCardId: number, visibleTargetIndex: number): number {
    const query = this.searchQuery.toLowerCase().trim();
    const visibleCards = targetColumn.cards.filter(card =>
      card.id !== movedCardId && this.matchesActiveFilters(card, query)
    );

    if (visibleCards.length === 0) {
      return targetColumn.cards.length;
    }

    if (visibleTargetIndex <= 0) {
      return targetColumn.cards.findIndex(card => card.id === visibleCards[0].id);
    }

    if (visibleTargetIndex >= visibleCards.length) {
      const lastVisibleCardIndex = targetColumn.cards.findIndex(card => card.id === visibleCards[visibleCards.length - 1].id);
      return lastVisibleCardIndex + 1;
    }

    return targetColumn.cards.findIndex(card => card.id === visibleCards[visibleTargetIndex].id);
  }

  private reindexColumn(column: BoardColumn): void {
    column.cards.forEach((card, index) => {
      card.position = index;
    });
  }

  private persistCardMove(columnContainerId: string, cardId: number, newPosition: number): void {
    const columnId = parseInt(columnContainerId.replace('col-', ''), 10);
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.put(`/api/projects/${this.projectId}/board/cards/${cardId}/move`, 
      { columnId, position: newPosition },
      { headers: { Authorization: `Bearer ${token}` } }
    )
    .pipe(takeUntilDestroyed(this.destroyRef))
    .subscribe({
      next: () => {
        // Todo bien, se guardó la nueva posición
      },
      error: (err) => {
        console.error('Error al mover la tarjeta', err);
        this.fetchBoard();
      }
    });
  }

  getPriorityColor(priority: string | null): string {
    switch(priority) {
      case 'high':
      case 'urgent':
        return 'bg-red-500 text-white';
      case 'medium':
        return 'bg-amber-500 text-white';
      case 'low':
        return 'bg-emerald-500 text-white';
      default:
        return 'bg-slate-600 text-slate-200';
    }
  }
  
  getPriorityLabel(priority: string | null): string {
    switch(priority) {
      case 'high': return 'Alta';
      case 'urgent': return 'Urgente';
      case 'medium': return 'Media';
      case 'low': return 'Baja';
      default: return 'Sin Prioridad';
    }
  }

  toggleFilter(filter: BoardFilter): void {
    this.activeFilters[filter] = !this.activeFilters[filter];
  }

  isFilterActive(filter: BoardFilter): boolean {
    return this.activeFilters[filter];
  }

  private matchesActiveFilters(card: Card, query: string): boolean {
    if (query && !this.matchesSearch(card, query)) {
      return false;
    }

    if (this.activeFilters.highPriority && !['high', 'urgent'].includes(card.priority ?? '')) {
      return false;
    }

    if (this.activeFilters.myTasks && card.assignee?.id !== this.currentUserId) {
      return false;
    }

    if (this.activeFilters.thisWeek && !this.isDueThisWeek(card.dueDate)) {
      return false;
    }

    return true;
  }

  private matchesSearch(card: Card, query: string): boolean {
    return card.title.toLowerCase().includes(query)
      || (card.description?.toLowerCase().includes(query) ?? false)
      || card.assignee?.name.toLowerCase().includes(query)
      || card.labels.some(label => label.name.toLowerCase().includes(query));
  }

  private isDueThisWeek(dueDate: string | null): boolean {
    if (!dueDate) return false;

    const date = new Date(dueDate);
    if (Number.isNaN(date.getTime())) return false;

    const today = new Date();
    const startOfWeek = new Date(today);
    const day = startOfWeek.getDay();
    const daysFromMonday = day === 0 ? 6 : day - 1;
    startOfWeek.setDate(startOfWeek.getDate() - daysFromMonday);
    startOfWeek.setHours(0, 0, 0, 0);

    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    endOfWeek.setHours(23, 59, 59, 999);

    return date >= startOfWeek && date <= endOfWeek;
  }

  get filteredColumns(): BoardColumn[] {
    if (!this.board) return [];
    
    const query = this.searchQuery.toLowerCase().trim();
    const hasFilters = this.activeFilters.highPriority || this.activeFilters.myTasks || this.activeFilters.thisWeek;
    if (!query && !hasFilters) return this.board.columns;
    
    // Clonación profunda básica para no alterar la matriz real con el filtro
    return this.board.columns.map(col => ({
      ...col,
      cards: col.cards.filter(c => this.matchesActiveFilters(c, query))
    }));
  }
}
