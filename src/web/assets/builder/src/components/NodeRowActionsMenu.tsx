import {
  Button,
  DropdownItem,
  DropdownMenu,
  DropdownSeparator,
  Icon,
} from '@verbb/plugin-kit-react/components';
import type { CSSProperties } from 'react';
import { cn } from '../utils/cn';
import type { BuilderNode } from '../types';
import { useBuilderStore } from '../store';
import { getNodeMoveCapabilities } from '../utils/treeOps';
import { suppressRowSelectionToggle } from '../utils/selection';
import { t } from '../api';

type Props = {
  node: BuilderNode;
  isDragSession?: boolean;
  className?: string;
};

type PkSelectDetail = { value?: string };

export function NodeRowActionsMenu({ node, isDragSession = false, className }: Props) {
  const nodes = useBuilderStore((s) => s.nodes);
  const maxLevels = useBuilderStore((s) => s.maxLevels);
  const openEditEditor = useBuilderStore((s) => s.openEditEditor);
  const moveNodeUp = useBuilderStore((s) => s.moveNodeUp);
  const moveNodeDown = useBuilderStore((s) => s.moveNodeDown);
  const indent = useBuilderStore((s) => s.indent);
  const outdent = useBuilderStore((s) => s.outdent);
  const deleteNode = useBuilderStore((s) => s.deleteNode);

  const capabilities = getNodeMoveCapabilities(nodes, node.key, maxLevels);
  const nodeTitle = node.title || t('Untitled');

  const handleMenuSelect = (event: Event) => {
    const value = (event as CustomEvent<PkSelectDetail>).detail?.value;

    if (!value) {
      return;
    }

    switch (value) {
      case 'edit':
        // Avoid menu-dismiss click-through hitting the row/label underneath.
        suppressRowSelectionToggle();
        openEditEditor(node.key);
        return;
      case 'move-up':
        moveNodeUp(node.key);
        return;
      case 'move-down':
        moveNodeDown(node.key);
        return;
      case 'move-left':
        outdent(node.key);
        return;
      case 'move-right':
        indent(node.key);
        return;
      case 'delete':
        if (confirm(t('Are you sure you want to delete this item?'))) {
          void deleteNode(node.key);
        }
        return;
      default:
        return;
    }
  };

  return (
    <div className={cn(isDragSession && 'opacity-0', className)}>
      <DropdownMenu size="sm" placement="bottom-end" onPkSelect={handleMenuSelect}>
        <Button
          slot="trigger"
          type="button"
          variant="transparent"
          size="sm"
          data-no-row-select
          style={{ '--pk-btn-height': '28px', '--pk-btn-icon-size': '12px' } as CSSProperties}
          aria-label={t('Actions for {title}', { title: nodeTitle })}
          onClick={(event) => event.stopPropagation()}
        >
          <Icon slot="start" icon="ellipsis" />
        </Button>

        <DropdownItem value="edit">
          <Icon slot="start" icon="pen" />
          {t('Edit')}
        </DropdownItem>

        <DropdownSeparator />

        <DropdownItem value="move-up" disabled={!capabilities.canMoveUp}>
          <Icon slot="start" icon="arrow-up" />
          {t('Move up')}
        </DropdownItem>
        <DropdownItem value="move-down" disabled={!capabilities.canMoveDown}>
          <Icon slot="start" icon="arrow-down" />
          {t('Move down')}
        </DropdownItem>
        <DropdownItem value="move-left" disabled={!capabilities.canOutdent}>
          <Icon slot="start" icon="arrow-left" />
          {t('Move left')}
        </DropdownItem>
        <DropdownItem value="move-right" disabled={!capabilities.canIndent}>
          <Icon slot="start" icon="arrow-right" />
          {t('Move right')}
        </DropdownItem>

        {node.deletable && (
          <>
            <DropdownSeparator />
            <DropdownItem value="delete" destructive>
              <Icon slot="start" icon="xmark" />
              {t('Delete')}
            </DropdownItem>
          </>
        )}
      </DropdownMenu>
    </div>
  );
}
