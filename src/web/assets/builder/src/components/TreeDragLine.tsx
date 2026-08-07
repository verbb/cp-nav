import type { DragLinePosition } from '../utils/dragLine';

type Props = {
  position: DragLinePosition | null;
};

/** Insert line aligned to our grid columns, centered in the row gap. */
export function TreeDragLine({ position }: Props) {
  if (!position) {
    return null;
  }

  return (
    <div
      className="pointer-events-none z-20 flex items-center"
      style={{
        position: 'absolute',
        top: `${position.top}px`,
        left: `${position.left}px`,
        right: `${position.right}px`,
        transform: 'translateY(-50%)',
        willChange: 'top, left',
      }}
    >
      <span className="size-2.5 shrink-0 rounded-full border-2 border-sky-500 bg-white" />
      <span className="h-0.5 min-w-0 flex-1 rounded-full bg-sky-500" />
    </div>
  );
}
