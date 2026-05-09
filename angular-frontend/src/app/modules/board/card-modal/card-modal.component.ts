import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, DestroyRef, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { finalize } from 'rxjs/operators';
import type { Assignee, BoardColumn, Card } from '../board.component';

type CardPriority = 'low' | 'medium' | 'high';

interface LabelOption {
  name: string;
  color: string;
}

interface CardCommentAuthor {
  id: number;
  name: string;
  avatarUrl: string | null;
}

interface CardComment {
  id: number;
  content: string;
  createdAt: string;
  author: CardCommentAuthor | null;
}

interface CommentsResponse {
  comments: CardComment[];
}

interface CommentResponse {
  comment: CardComment;
}

interface UploadImageResponse {
  url: string;
}

interface ContentSegment {
  type: 'text' | 'image';
  value: string;
  alt?: string;
}

@Component({
  selector: 'app-card-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './card-modal.component.html',
})
export class CardModalComponent implements OnChanges {
  @Input({ required: true }) projectId!: number;
  @Input({ required: true }) columns: BoardColumn[] = [];
  @Input({ required: true }) assignees: Assignee[] = [];
  @Input() card: Card | null = null;
  @Input() initialColumnId = 0;
  @Input() currentUserId: number | null = null;

  @Output() closed = new EventEmitter<void>();
  @Output() saved = new EventEmitter<void>();

  title = '';
  description = '';
  columnId = 0;
  assigneeId: number | null = null;
  priority: CardPriority = 'medium';
  dueDate = '';
  selectedLabelName = '';
  isSaving = false;
  error = '';
  comments: CardComment[] = [];
  newComment = '';
  isLoadingComments = false;
  isSavingComment = false;
  isUploadingDescriptionImage = false;
  isUploadingCommentImage = false;
  commentError = '';
  descriptionImageError = '';
  commentImageError = '';
  readonly labelOptions: LabelOption[] = [
    { name: 'Bug', color: '#EF4444' },
    { name: 'Feature', color: '#3B82F6' },
    { name: 'Frontend', color: '#10B981' },
    { name: 'Backend', color: '#8B5CF6' },
    { name: 'Design', color: '#EC4899' },
    { name: 'Marketing', color: '#F59E0B' },
    { name: 'QA', color: '#14B8A6' },
    { name: 'Docs', color: '#64748B' },
  ];

  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);

  get isEditing(): boolean {
    return this.card !== null;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['card'] || changes['initialColumnId']) {
      this.resetForm();
      this.loadComments();
    }
  }

  close(): void {
    this.closed.emit();
  }

  save(): void {
    const trimmedTitle = this.title.trim();
    if (!trimmedTitle) {
      this.error = 'El titulo de la tarea es obligatorio.';
      return;
    }

    if (!this.columnId) {
      this.error = 'Selecciona una columna.';
      return;
    }

    this.isSaving = true;
    this.error = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');
    const payload = {
      title: trimmedTitle,
      description: this.description.trim(),
      columnId: this.columnId,
      assigneeId: this.assigneeId || null,
      priority: this.priority,
      dueDate: this.dueDate || null,
      labels: this.selectedLabelName ? [this.selectedLabelName] : [],
    };

    const request = this.isEditing && this.card
      ? this.http.put(`/api/projects/${this.projectId}/board/cards/${this.card.id}`, payload, {
          headers: { Authorization: `Bearer ${token}` },
        })
      : this.http.post(`/api/projects/${this.projectId}/board/columns/${this.columnId}/cards`, payload, {
          headers: { Authorization: `Bearer ${token}` },
        });

    request
      .pipe(
        finalize(() => { this.isSaving = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.saved.emit();
        },
        error: (err) => {
          this.error = err.error?.message ?? 'No se pudo guardar la tarea.';
        },
      });
  }

  assignToMe(event: Event): void {
    event.preventDefault();
    if (this.currentUserId !== null && this.currentUserId !== undefined) {
      this.assigneeId = this.currentUserId;
    }
  }

  attachDescriptionImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || this.isUploadingDescriptionImage) return;

    this.uploadImage(file, 'description');
  }

  attachCommentImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || this.isUploadingCommentImage) return;

    this.uploadImage(file, 'comment');
  }

  saveComment(): void {
    if (!this.card || this.isSavingComment) return;

    const content = this.newComment.trim();
    if (!content) {
      this.commentError = 'Escribe un comentario.';
      return;
    }

    this.isSavingComment = true;
    this.commentError = '';

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<CommentResponse>(`/api/projects/${this.projectId}/board/cards/${this.card.id}/comments`,
      { content },
      { headers: { Authorization: `Bearer ${token}` } }
    )
      .pipe(
        finalize(() => { this.isSavingComment = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.comments = [...this.comments, res.comment];
          this.newComment = '';
          this.card!.commentCount = this.comments.length;
        },
        error: (err) => {
          this.commentError = err.error?.message ?? 'No se pudo guardar el comentario.';
        },
      });
  }

  getDescriptionSegments(): ContentSegment[] {
    return this.parseImageMarkdown(this.description);
  }

  getCommentSegments(content: string): ContentSegment[] {
    return this.parseImageMarkdown(content);
  }

  private resetForm(): void {
    this.error = '';
    this.title = this.card?.title ?? '';
    this.description = this.card?.description ?? '';
    this.columnId = this.initialColumnId || this.columns[0]?.id || 0;
    this.assigneeId = this.card?.assignee?.id ?? null;
    this.priority = this.normalizePriority(this.card?.priority);
    this.dueDate = this.card?.dueDate ? this.card.dueDate.slice(0, 10) : '';
    this.selectedLabelName = this.normalizeLabel(this.card?.labels[0]?.name);
    this.descriptionImageError = '';
  }

  private loadComments(): void {
    this.comments = [];
    this.newComment = '';
    this.commentError = '';

    if (!this.card) return;

    this.isLoadingComments = true;
    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.get<CommentsResponse>(`/api/projects/${this.projectId}/board/cards/${this.card.id}/comments`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(
        finalize(() => { this.isLoadingComments = false; }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.comments = res.comments;
          this.card!.commentCount = res.comments.length;
        },
        error: (err) => {
          this.commentError = err.error?.message ?? 'No se pudieron cargar los comentarios.';
        },
      });
  }

  private normalizePriority(priority: string | null | undefined): CardPriority {
    if (priority === 'low' || priority === 'medium' || priority === 'high') {
      return priority;
    }

    return 'medium';
  }

  private normalizeLabel(labelName: string | undefined): string {
    return this.labelOptions.some(option => option.name === labelName) ? labelName ?? '' : '';
  }

  private uploadImage(file: File, target: 'description' | 'comment'): void {
    if (!file.type.match(/^image\/(jpeg|png|webp|gif)$/)) {
      this.setImageError(target, 'Formato de imagen no valido.');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      this.setImageError(target, 'La imagen no puede superar los 5 MB.');
      return;
    }

    const formData = new FormData();
    formData.append('image', file);

    if (target === 'description') {
      this.isUploadingDescriptionImage = true;
      this.descriptionImageError = '';
    } else {
      this.isUploadingCommentImage = true;
      this.commentImageError = '';
    }

    const token = localStorage.getItem('jwt_token') || sessionStorage.getItem('jwt_token');

    this.http.post<UploadImageResponse>(`/api/projects/${this.projectId}/board/uploads/images`, formData, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .pipe(
        finalize(() => {
          if (target === 'description') {
            this.isUploadingDescriptionImage = false;
          } else {
            this.isUploadingCommentImage = false;
          }
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (res) => {
          this.appendImageMarkdown(target, res.url, file.name);
        },
        error: (err) => {
          this.setImageError(target, err.error?.message ?? 'No se pudo subir la imagen.');
        },
      });
  }

  private appendImageMarkdown(target: 'description' | 'comment', url: string, fileName: string): void {
    const markdown = `![${fileName}](${url})`;

    if (target === 'description') {
      this.description = this.appendBlock(this.description, markdown);
      return;
    }

    this.newComment = this.appendBlock(this.newComment, markdown);
  }

  private appendBlock(currentValue: string, block: string): string {
    const trimmedEnd = currentValue.replace(/\s+$/, '');
    return trimmedEnd ? `${trimmedEnd}\n\n${block}` : block;
  }

  private setImageError(target: 'description' | 'comment', message: string): void {
    if (target === 'description') {
      this.descriptionImageError = message;
      return;
    }

    this.commentImageError = message;
  }

  private parseImageMarkdown(content: string): ContentSegment[] {
    const segments: ContentSegment[] = [];
    const imagePattern = /!\[([^\]]*)\]\(([^)\s]+)\)/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = imagePattern.exec(content)) !== null) {
      const text = content.slice(lastIndex, match.index);
      if (text) {
        segments.push({ type: 'text', value: text });
      }

      segments.push({ type: 'image', value: match[2], alt: match[1] || 'Imagen adjunta' });
      lastIndex = imagePattern.lastIndex;
    }

    const remainingText = content.slice(lastIndex);
    if (remainingText) {
      segments.push({ type: 'text', value: remainingText });
    }

    return segments;
  }
}
