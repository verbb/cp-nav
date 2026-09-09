import { useEffect, useMemo } from 'react';
import {
  dragAndDropFeature,
  syncDataLoaderFeature,
  type DragTarget,
  type ItemInstance,
} from '@headless-tree/core';
import { useTree } from '@headless-tree/react';
import { cn } from '../utils/cn';
import { useBuilderStore } from '../store';
import { t } from '../api';
import {
  CPNAV_TREE_ROOT_ID,
  buildTreeChildrenMap,
  flattenTree,
  getExpandedTreeItemIds,
  refreshHasDescendants,
  removeIdsFromChildrenMap,
} from '../utils/headlessTreeData';
import { NODE_LEVEL_INDENT_PX, nodeTreeGridClass, nodeTreeSecondaryColumnClass } from '../utils/nodeRowLayout';
import type { BuilderNode } from '../types';
import { NodeRow } from './NodeRow';
import { TreeDragLine } from './TreeDragLine';
import { useCpNavDragSession } from '../hooks/useCpNavDragSession';

const ROOT_NODE: BuilderNode = {
  builderId: 0,
  key: CPNAV_TREE_ROOT_ID,
  title: 'Root',
  label: '',
  defaultLabel: null,
  url: null,
  type: 'craft',
  typeLabel: '',
  typeClass: '',
  typeColorRgb: '',
  typeTextColorRgb: '',
  enabled: true,
  level: 0,
  parentId: null,
  parentKey: null,
  sort: 0,
  newWindow: false,
  icon: null,
  customIcon: null,
  customIconPreview: null,
  isCustomized: false,
  isNew: false,
  isOrphan: false,
  canIndent: false,
  canOutdent: false,
  deletable: false,
  hasDescendants: true,
};

export function NodeTree() {
  const allNodes = useBuilderStore((s) => s.nodes);
  const maxLevels = useBuilderStore((s) => s.maxLevels);
  const collapsedNodeKeys = useBuilderStore((s) => s.collapsedNodeKeys);
  const setNodes = useBuilderStore((s) => s.setNodes);

  const showTypeColumn = true;
  const treeNodes = allNodes;

  const nodeMap = useMemo(() => new Map(allNodes.map((node) => [node.key, node])), [allNodes]);
  const childrenMap = useMemo(() => buildTreeChildrenMap(treeNodes), [treeNodes]);

  const expandedItems = useMemo(
    () => getExpandedTreeItemIds(treeNodes, collapsedNodeKeys),
    [treeNodes, collapsedNodeKeys],
  );

  const applyTreeStructure = (nextChildrenMap: typeof childrenMap) => {
    setNodes(refreshHasDescendants(flattenTree(nodeMap, nextChildrenMap)));
  };

  const handleDrop = (items: ItemInstance<BuilderNode>[], target: DragTarget<BuilderNode>) => {
    const draggedIds = new Set(items.map((item) => item.getId()));
    const nextMap = removeIdsFromChildrenMap(childrenMap, draggedIds);

    const targetParentId = target.item.getId();
    const currentChildren = [...(nextMap[targetParentId] ?? [])];
    const draggedIdList = items.map((item) => item.getId());

    if ('childIndex' in target) {
      nextMap[targetParentId] = [
        ...currentChildren.slice(0, target.insertionIndex),
        ...draggedIdList,
        ...currentChildren.slice(target.insertionIndex),
      ];
    } else {
      nextMap[targetParentId] = [...currentChildren, ...draggedIdList];
    }

    applyTreeStructure(nextMap);
  };

  const tree = useTree<BuilderNode>({
    rootItemId: CPNAV_TREE_ROOT_ID,
    state: {
      expandedItems,
    },
    setExpandedItems: (updater) => {
      const { expandNodeCollapsed, toggleNodeCollapsed, collapsedNodeKeys: storedCollapsed } =
        useBuilderStore.getState();
      const prev = getExpandedTreeItemIds(treeNodes, storedCollapsed);
      const next = typeof updater === 'function' ? updater(prev) : updater;
      const nextExpanded = new Set(next);

      for (const node of treeNodes) {
        if (!node.hasDescendants) {
          continue;
        }

        const shouldBeExpanded = nextExpanded.has(node.key);
        const isCollapsed = Boolean(storedCollapsed[node.key]);

        if (shouldBeExpanded && isCollapsed) {
          expandNodeCollapsed(node.key);
        } else if (!shouldBeExpanded && !isCollapsed) {
          toggleNodeCollapsed(node.key);
        }
      }
    },
    getItemName: (item) => item.getItemData()?.title || '(Untitled)',
    isItemFolder: (item) => {
      const nodeLevel = item.getItemMeta().level + 1;

      return nodeLevel < maxLevels;
    },
    dataLoader: {
      getItem: (itemId) => {
        if (itemId === CPNAV_TREE_ROOT_ID) {
          return ROOT_NODE;
        }

        return nodeMap.get(itemId) ?? ROOT_NODE;
      },
      getChildren: (itemId) => childrenMap[itemId] ?? [],
    },
    indent: NODE_LEVEL_INDENT_PX,
    features: [syncDataLoaderFeature, dragAndDropFeature],
    seperateDragHandle: true,
    // Top/bottom edge bands for insert-line; middle band is drop-as-child.
    reorderAreaPercentage: 0.2,
    openOnDropDelay: 500,
    canDrop: (items, target) => {
      const targetParentId = target.item.getId();
      const targetParent =
        targetParentId === CPNAV_TREE_ROOT_ID ? null : (nodeMap.get(targetParentId) ?? null);

      // Nothing nests under a divider section break.
      if (targetParent?.type === 'divider') {
        return false;
      }

      const draggedNodes = items
        .map((item) => item.getItemData())
        .filter((node): node is BuilderNode => Boolean(node));

      // Dividers stay top-level only.
      if (draggedNodes.some((node) => node.type === 'divider')) {
        if ('dragLineLevel' in target) {
          if (target.dragLineLevel + 1 > 1) {
            return false;
          }
        } else if (targetParentId !== CPNAV_TREE_ROOT_ID) {
          return false;
        }
      }

      if ('dragLineLevel' in target) {
        const targetLevel = target.dragLineLevel + 1;
        const draggedRoot = items[0];
        const draggedNode = draggedRoot?.getItemData();

        if (!draggedNode) {
          return true;
        }

        const subtreeDepth = getSubtreeDepthFromMap(childrenMap, draggedRoot.getId());

        if (targetLevel + subtreeDepth - 1 > maxLevels) {
          return false;
        }
      }

      return true;
    },
    createForeignDragObject: (items) => ({
      format: 'application/x-cpnav-node',
      data: items.map((item) => item.getId()).join(','),
      effectAllowed: 'move',
    }),
    setDragImage: () => {
      const element = document.createElement('div');
      element.style.width = '1px';
      element.style.height = '1px';
      element.style.opacity = '0';
      element.style.position = 'fixed';
      element.style.top = '-9999px';
      document.body.appendChild(element);

      return {
        imgElement: element,
        xOffset: 0,
        yOffset: 0,
      };
    },
    onDrop: handleDrop,
  });

  useEffect(() => {
    tree.rebuildTree();
  }, [tree, childrenMap, expandedItems, treeNodes]);

  const draggedIds = useMemo(() => {
    const dragged = tree.getState().dnd?.draggedItems;

    if (!dragged?.length) {
      return new Set<string>();
    }

    return new Set(dragged.map((item) => item.getId()));
  }, [tree, tree.getState().dnd]);

  const isDragSession = Boolean(tree.getState().dnd);
  const { linePosition, nestTargetId } = useCpNavDragSession(tree, showTypeColumn, isDragSession);

  if (!allNodes.length) {
    return (
      <div className="flex min-h-[320px] items-center justify-center border border-dashed border-gray-200 p-12 text-sm text-gray-500">
        <p>{t('No navigation items yet. Use the sidebar to add a manual link or divider.')}</p>
      </div>
    );
  }

  const items = tree.getItems().filter((item) => item.getId() !== CPNAV_TREE_ROOT_ID);
  const containerProps = tree.getContainerProps(t('Navigation nodes'));
  const { onDragOver: treeOnDragOver, ...treeContainerProps } = containerProps;

  return (
    <div className="@container w-full min-w-0" data-cpnav-tree-container="">
      <div role="table" className="w-full min-w-0 text-sm">
        <div
          role="rowgroup"
          className={nodeTreeGridClass(
            showTypeColumn,
            'bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500',
          )}
        >
          <div role="columnheader" className="px-2 py-2.5 text-center font-medium normal-case">
            {t('Show')}
          </div>
          <div role="columnheader" className="px-3 py-2.5 font-medium normal-case">
            {t('Label')}
          </div>
          <div
            role="columnheader"
            className={nodeTreeSecondaryColumnClass('items-center px-3 py-2.5 font-medium normal-case')}
          >
            {t('URL')}
          </div>
          {showTypeColumn && (
            <div
              role="columnheader"
              className={nodeTreeSecondaryColumnClass(
                'items-center justify-end py-2.5 pr-2 pl-3 text-right font-medium normal-case',
              )}
            >
              {t('Type')}
            </div>
          )}
          <div role="columnheader" className="py-2.5" aria-hidden />
        </div>

        <div role="rowgroup" className="select-none">
          <div
            {...treeContainerProps}
            className={cn('relative pb-8', isDragSession && 'cursor-grabbing')}
            onDragOver={(event) => {
              treeOnDragOver?.(event);
              if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
              }
            }}
          >
            <TreeDragLine position={linePosition} />
            {items.map((item) => (
              <HeadlessTreeRow
                key={item.getId()}
                item={item}
                showTypeColumn={showTypeColumn}
                isDragging={draggedIds.has(item.getId())}
                isDragSession={isDragSession}
                isDropNestTarget={nestTargetId === item.getId()}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

type RowProps = {
  item: ItemInstance<BuilderNode>;
  showTypeColumn: boolean;
  isDragging: boolean;
  isDragSession: boolean;
  isDropNestTarget: boolean;
};

function HeadlessTreeRow({ item, showTypeColumn, isDragging, isDragSession, isDropNestTarget }: RowProps) {
  const node = item.getItemData();
  const itemProps = item.getProps();

  if (!node || node.key === CPNAV_TREE_ROOT_ID) {
    return null;
  }

  return (
    <NodeRow
      node={node}
      item={item}
      itemProps={itemProps}
      showTypeColumn={showTypeColumn}
      isDragging={isDragging}
      isDragSession={isDragSession}
      isDropNestTarget={isDropNestTarget}
    />
  );
}

function getSubtreeDepthFromMap(childrenMap: Record<string, string[]>, rootId: string): number {
  const childIds = childrenMap[rootId] ?? [];

  if (childIds.length === 0) {
    return 1;
  }

  return 1 + Math.max(...childIds.map((childId) => getSubtreeDepthFromMap(childrenMap, childId)));
}
