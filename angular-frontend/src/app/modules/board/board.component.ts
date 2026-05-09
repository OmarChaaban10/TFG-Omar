import { Component, OnInit, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';
import { DragDropModule, CdkDragDrop } from '@angular/cdk/drag-drop';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ThemeToggleComponent } from '../shared/theme-toggle/theme-toggle.component';
import { CardModalComponent } from './card-modal/card-modal.component';

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
  assignees: Assignee[];
}

interface ColumnResponse {
  column: BoardColumn;
}

type BoardFilter = 'highPriority' | 'myTasks' | 'thisWeek';

interface SelectedBoardProject {
  id: number;
  name: string;
}

@Component({
  selector: 'app-board',
  standalone: true,
  imports: [CommonModule, DragDropModule, FormsModule, ThemeToggleComponent, CardModalComponent],
  templateUrl: './board.component.html'
})
export class BoardComponent implements OnInit {
  projectId = 0;
  projectName = '';
  board: Board | null = null;
  currentUserId: number | null = null;
  assignees: Assignee[] = [];
  isLoading = false;
  error = '';
  searchQuery = '';
  showCardModal = false;
  selectedCard: Card | null = null;
  selectedColumnId = 0;
  showColumnForm = false;
  newColumnName = '';
  newColumnColor = '#FB923C';
  isCreatingColumn = false;
  columnCreateError = '';
  showDeleteColumnForm = false;
  columnToDeleteId = 0;
  columnPendingDeletion: BoardColumn | null = null;
  showDeleteColumnConfirm = false;
  isDeletingColumn = false;
  columnDeleteError = '';
  readonly columnColorOptions = ['#FB923C', '#38BDF8', '#A78BFA', '#34D399', '#F87171', '#E2E8F0'];
  activeFilters: Record<BoardFilter, boolean> = {
    highPriority: false,
    myTasks: false,
    thisWeek: false,
  };
  
  // Para los id de las listas conectadas de drag & drop
  connectedLists: string[] = [];

  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private loadedProjectId: number | null = null;

  ngOnInit(): void {
    const selectedProject = this.getSelectedProject();

    if (selectedProject) {
      this.loadProject(selectedProject.id, selectedProject.name);
      return;
    }

    this.error = 'Selecciona un proyecto desde el dashboard para abrir su tablero.';
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

  private getSelectedProject(): SelectedBoardProject | null {
    const state = window.history.state as { projectId?: unknown; projectName?: unknown };
    const stateProjectId = Number(state.projectId);

    if (Number.isInteger(stateProjectId) && stateProjectId > 0) {
      return {
        id: stateProjectId,
        name: typeof state.projectName === 'string' ? state.projectName : '',
      };
    }

    const storedProject = sessionStorage.getItem('selected_board_project');
    if (!storedProject) return null;

    try {
      const parsed = JSON.parse(storedProject) as { id?: unknown; name?: unknown };
      const id = Number(parsed.id);
      if (!Number.isInteger(id) || id <= 0) return null;

      return {
        id,
        name: typeof parsed.name === 'string' ? parsed.name : '',
      };
    } catch {
      return null;
    }
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
          this.assignees = res.assignees;
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

  openCreateCardModal(column: BoardColumn): void {
    this.selectedCard = null;
    this.selectedColumnId = column.id;
    this.showCardModal = true;
  }

  openEditCardModal(card: Card): void {
    this.selectedCard = card;
    this.selectedColumnId = this.getColumnIdForCard(card.id);
    this.showCardModal = true;
  }

  closeCardModal(): void {
    this.showCardModal = false;
    this.selectedCard = null;
    this.selectedColumnId = 0;
  }

  handleCardSaved(): void {
    this.closeCardModal();
    this.fetchBoard();
  }

  openCreateColumnForm(): void {
    this.showColumnForm = true;
    this.showDeleteColumnForm = false;
    this.columnCreateError = '';
  }

  cancelCreateColumn(): void {
    this.showColumnForm = false;
    this.newColumnName = '';
    this.newColumnColor = '#FB923C';
    this.columnCreateError = '';
  }

  openDeleteColumnForm(): void {
    this.showDeleteColumnForm = true;
    this.showColumnForm = false;
    this.columnDeleteError = '';
    this.columnToDeleteId = this.columnToDeleteId || this.board?.columns[0]?.id || 0;
  }

  cancelDeleteColumn(): void {
    if (this.isDeletingColumn) return;

    this.showDeleteColumnForm = false;
    this.showDeleteColumnConfirm = false;
    this.columnToDeleteId = 0;
    this.columnPendingDeletion = null;
    this.columnDeleteError = '';
  }

  createColumn(): void {
    if (!this.board || this.isCreatingColumn) return;

    const name = this.newColumnName.trim();
    if (!name) {
      this.columnCreateError = 'El nombre de la columna es obligatorio.';
      return;
    }

    this.isCreatingColumn = true;
    this.columnCreateError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<ColumnResponse>(`/api/projects/${this.projectId}/board/columns`,
      { name, color: this.newColumnColor },
      { headers: { Authorization: `Bearer ${token}` } }
    )
      .pipe(
        finalize(() => { this.isCreatingColumn = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          if (!this.board) return;

          this.board.columns = [...this.board.columns, res.column]
            .sort((a, b) => a.position - b.position);
          this.connectedLists = this.board.columns.map(column => `col-${column.id}`);
          this.cancelCreateColumn();
        },
        error: (err) => {
          this.columnCreateError = err.error?.message || 'No se pudo crear la columna.';
        },
      });
  }

  deleteColumn(): void {
    if (!this.board) return;

    const column = this.board.columns.find(item => item.id === this.columnToDeleteId);
    if (!column) {
      this.columnDeleteError = 'Selecciona una columna.';
      return;
    }

    if (this.board.columns.length <= 1) {
      this.columnDeleteError = 'No puedes eliminar la ultima columna del tablero.';
      return;
    }

    this.columnDeleteError = '';
    this.columnPendingDeletion = column;
    this.showDeleteColumnConfirm = true;
  }

  closeDeleteColumnConfirm(): void {
    if (this.isDeletingColumn) return;

    this.showDeleteColumnConfirm = false;
    this.columnPendingDeletion = null;
  }

  confirmDeleteColumn(): void {
    if (!this.board || this.isDeletingColumn || !this.columnPendingDeletion) {
      return;
    }

    const column = this.columnPendingDeletion;
    this.isDeletingColumn = true;
    this.columnDeleteError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.delete(`/api/projects/${this.projectId}/board/columns/${column.id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(
        finalize(() => { this.isDeletingColumn = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          if (!this.board) return;

          this.board.columns = this.board.columns
            .filter(item => item.id !== column.id)
            .map((item, index) => ({ ...item, position: index + 1 }));
          this.connectedLists = this.board.columns.map(item => `col-${item.id}`);
          this.isDeletingColumn = false;
          this.cancelDeleteColumn();
        },
        error: (err) => {
          this.columnDeleteError = err.error?.message || 'No se pudo eliminar la columna.';
        },
      });
  }

  private getColumnIdForCard(cardId: number): number {
    return this.board?.columns.find(column =>
      column.cards.some(card => card.id === cardId)
    )?.id ?? 0;
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
