import { cn } from '../utils/cn';
import { getTypeMeta } from '../utils/typeMeta';
import type { BuilderNode } from '../types';

type Props = {
  node: BuilderNode;
};

export function NodeTypeBadge({ node }: Props) {
  const meta = getTypeMeta(node.type);
  const label = node.typeLabel || meta.label;
  const colorRgb = node.typeColorRgb || meta.colorRgb;
  const textColorRgb = node.typeTextColorRgb || meta.textColorRgb;

  return (
    <div className="cursor-default select-none text-right text-[10px] font-semibold uppercase tracking-wide">
      <span
        className={cn('inline-block whitespace-nowrap rounded border border-transparent px-1 py-0.5', node.typeClass || meta.typeClass)}
        title={node.url ?? undefined}
        style={{
          color: `rgb(${textColorRgb})`,
          backgroundColor: `rgba(${colorRgb}, 0.1)`,
          borderColor: `rgba(${textColorRgb}, 0.35)`,
        }}
      >
        {label}
      </span>
    </div>
  );
}
