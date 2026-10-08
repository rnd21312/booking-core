import type { ReactNode } from 'react';
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  rectSortingStrategy,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import { __ } from '@wordpress/i18n';

type RowProps = {
  id: string;
  children: (handle: ReactNode) => ReactNode;
};

const SortableRow = ({ id, children }: RowProps) => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } =
    useSortable({ id });

  const handle = (
    <button
      type="button"
      ref={setActivatorNodeRef}
      className="stz-handle"
      aria-label={__('Drag to reorder (or focus and press Space, then use arrow keys)', 'suntourz')}
      {...attributes}
      {...listeners}
    >
      <GripVertical size={16} aria-hidden />
    </button>
  );

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={`stz-sortable-item${isDragging ? ' is-dragging' : ''}`}
    >
      {children(handle)}
    </div>
  );
};

type SortableListProps<T extends { _key: string }> = {
  items: T[];
  onChange: (items: T[]) => void;
  /** Receives the drag handle to place anywhere inside the row. */
  renderItem: (item: T, index: number, handle: ReactNode) => ReactNode;
  layout?: 'list' | 'grid';
};

/** Accessible drag-and-drop list (pointer + keyboard) keyed by each item's `_key`. */
export const SortableList = <T extends { _key: string }>({
  items,
  onChange,
  renderItem,
  layout = 'list',
}: SortableListProps<T>) => {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const onDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const from = items.findIndex((item) => item._key === active.id);
    const to = items.findIndex((item) => item._key === over.id);
    if (from >= 0 && to >= 0) onChange(arrayMove(items, from, to));
  };

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
      <SortableContext
        items={items.map((item) => item._key)}
        strategy={layout === 'grid' ? rectSortingStrategy : verticalListSortingStrategy}
      >
        {items.map((item, index) => (
          <SortableRow key={item._key} id={item._key}>
            {(handle) => renderItem(item, index, handle)}
          </SortableRow>
        ))}
      </SortableContext>
    </DndContext>
  );
};
