import { type DragEvent, type MouseEvent } from 'react';
import type { ItemInstance } from '@headless-tree/core';
import { Button, Icon, Lightswitch } from '@verbb/plugin-kit-react/components';
import { cn } from '../utils/cn';
import type { BuilderNode } from '../types';
import { useBuilderStore } from '../store';
import { t } from '../api';
import { getNodeRowPaddingLeft, nodeTreeGridClass, nodeTreeSecondaryColumnClass } from '../utils/nodeRowLayout';
import { shouldSuppressRowSelectionToggle } from '../utils/selection';
import { NodeTypeBadge } from './NodeTypeBadge';
import { NodeRowActionsMenu } from './NodeRowActionsMenu';

type Props = {
  node: BuilderNode;
  item: ItemInstance<BuilderNode>;
  itemProps: Record<string, unknown>;
  showTypeColumn?: boolean;
  isDragging?: boolean;
  isDragSession?: boolean;
  isDropNestTarget?: boolean;
};

export function NodeRow({
  node,
  item,
  itemProps,
  showTypeColumn = true,
  isDragging = false,
  isDragSession = false,
  isDropNestTarget = false,
}: Props) {
  const openEditEditor = useBuilderStore((s) => s.openEditEditor);
  const toggleEnabled = useBuilderStore((s) => s.toggleEnabled);
  const collapsedNodeKeys = useBuilderStore((s) => s.collapsedNodeKeys);
  const toggleNodeCollapsed = useBuilderStore((s) => s.toggleNodeCollapsed);

  const {
    ref: registerElement,
    onClick: _treeOnClick,
    role: _treeRole,
    onDragOver: treeOnDragOver,
    ...dragProps
  } = itemProps as {
    ref?: (element: HTMLElement | null) => void;
    onClick?: (event: MouseEvent) => void;
    onDragOver?: (event: DragEvent) => void;
    role?: string;
  } & Record<string, unknown>;

  const { onDragStart: treeOnDragStart, ...dragHandleProps } = item.getDragHandleProps() as {
    onDragStart?: (event: DragEvent) => void;
  } & Record<string, unknown>;

  const paddingLeft = getNodeRowPaddingLeft(node.level);
  const isCollapsed = Boolean(collapsedNodeKeys[node.key]);
  const title = node.title || t('(Untitled)');
  // Always show the registry/default title when present (matches the classic CP Nav table).
  const defaultLabel = node.defaultLabel?.trim() || null;

  const openEditor = () => {
    if (shouldSuppressRowSelectionToggle()) {
      return;
    }

    openEditEditor(node.key);
  };

  return (
    <div
      ref={registerElement}
      role="row"
      {...dragProps}
      onDragOver={(event) => {
        treeOnDragOver?.(event);
        if (event.dataTransfer) {
          event.dataTransfer.dropEffect = 'move';
        }
      }}
      className={cn(
        nodeTreeGridClass(showTypeColumn, 'group min-h-9 outline-none focus:outline-none focus-visible:outline-none'),
        !isDragSession && 'hover:bg-gray-50',
        isDropNestTarget && 'bg-sky-50',
        isDragging && 'opacity-40',
        !node.enabled && 'opacity-70',
      )}
      data-key={node.key}
      data-tree-row=""
      data-level={node.level}
    >
      <div role="cell" className="flex items-center justify-center px-2 py-1">
        <Lightswitch
          size="sm"
          data-no-row-select
          aria-label={t('Show {title}', { title })}
          checked={node.enabled}
          onCheckedChange={(checked) => void toggleEnabled(node.key, checked)}
          onClick={(event: MouseEvent) => event.stopPropagation()}
        />
      </div>

      <div
        role="cell"
        className="flex min-h-9 min-w-0 items-center gap-1.5 overflow-hidden py-1 pr-3"
        style={{ paddingLeft: paddingLeft + 12 }}
      >
        <span className="relative inline-flex w-3 shrink-0 justify-center">
          {node.hasDescendants ? (
            <button
              type="button"
              data-no-row-select
              className="absolute top-1/2 left-1/2 flex size-6 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded bg-transparent text-gray-400 hover:bg-transparent hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600/40"
              aria-expanded={!isCollapsed}
              onClick={(event) => {
                event.stopPropagation();
                toggleNodeCollapsed(node.key);
              }}
            >
              <Icon
                icon="chevron-right"
                className={cn('size-2.5 transition-transform duration-150', !isCollapsed && 'rotate-90')}
              />
            </button>
          ) : null}
        </span>

        <span className="relative inline-flex shrink-0">
          <Button
            type="button"
            variant="none"
            size="xs"
            data-no-row-select
            className="absolute top-1/2 left-1/2 flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded bg-transparent p-0 text-gray-400 outline-none hover:bg-transparent hover:text-gray-600 focus:outline-none focus-visible:outline-none focus-visible:ring-0 [&::part(base)]:cursor-move"
            title={t('Drag to reorder')}
            onClick={(event) => event.stopPropagation()}
            onDragStart={(event) => {
              if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
              }
              treeOnDragStart?.(event);
            }}
            {...dragHandleProps}
          >
            <Icon slot="start" icon="grip-move" className="size-3.5" />
          </Button>
          <span className="invisible inline-flex size-3.5" aria-hidden>
            <Icon icon="grip-move" className="size-3.5" />
          </span>
        </span>

        {node.type === 'divider' ? (
          <button
            type="button"
            data-no-row-select
            data-cpnav-editor-anchor={node.key}
            className="flex min-w-0 flex-1 items-center gap-2 bg-transparent p-0 text-left"
            onClick={(event) => {
              event.stopPropagation();
              openEditor();
            }}
          >
            <span className="h-px min-w-8 flex-1 bg-gray-300" aria-hidden="true" />
            <span
              className={cn(
                'shrink-0 text-[10px] font-semibold tracking-wide text-gray-500 uppercase',
                !node.enabled && 'text-gray-400',
              )}
            >
              {title.trim() !== '' ? title : t('Divider')}
            </span>
            <span className="h-px min-w-8 flex-1 bg-gray-300" aria-hidden="true" />
          </button>
        ) : (
          <a
            href="#"
            data-no-row-select
            data-cpnav-editor-anchor={node.key}
            className={cn(
              'min-w-0 truncate font-normal text-link no-underline hover:underline',
              !node.enabled && 'text-gray-400',
            )}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              openEditor();
            }}
          >
            {title}
          </a>
        )}

        {node.type !== 'divider' && defaultLabel && (
          <span className="min-w-0 truncate text-gray-400">({defaultLabel})</span>
        )}

        {node.newWindow && (
          <Icon
            icon="arrow-up-right-from-square"
            className="size-2.5 shrink-0 text-gray-400/80"
            label={t('Opens in a new window')}
          />
        )}

        {node.isNew && (
          <span className="shrink-0 rounded bg-sky-100 px-1 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
            {t('New')}
          </span>
        )}
      </div>

      <div
        role="cell"
        className={nodeTreeSecondaryColumnClass(
          cn(
            'min-w-0 items-center px-3 py-1 text-sm text-gray-700',
            !node.enabled && 'text-gray-400',
          ),
        )}
      >
        <span className="truncate" title={node.url ?? undefined}>
          {node.url || ''}
        </span>
      </div>

      {showTypeColumn && (
        <div role="cell" className={nodeTreeSecondaryColumnClass('items-center justify-end px-1 py-1')}>
          <NodeTypeBadge node={node} />
        </div>
      )}

      <div
        role="cell"
        className={cn(
          'flex items-center justify-center py-1 pr-1 pl-0.5',
          isDropNestTarget ? 'bg-sky-50' : 'bg-white group-hover:bg-gray-50',
        )}
      >
        <NodeRowActionsMenu node={node} isDragSession={isDragSession} />
      </div>
    </div>
  );
}
