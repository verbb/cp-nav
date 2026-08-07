import {
  configurePluginKitReact,
  mountShadowApp,
  type TranslateFunction,
} from '@verbb/plugin-kit-react/utils';

declare const Craft: {
  t: TranslateFunction;
};

export const ensureCraftNamespace = (namespacePath: string) => {
  const parts = namespacePath.split('.').map((part) => part.trim()).filter(Boolean);
  let current: Record<string, unknown> = (window as typeof window & { Craft: Record<string, unknown> }).Craft;

  parts.forEach((part) => {
    if (typeof current[part] === 'undefined') {
      current[part] = {};
    }

    current = current[part] as Record<string, unknown>;
  });

  return current;
};

export type BuilderShadowConfig = {
  pluginHandle: string;
  styleTexts: string[];
  styleNamespace?: string;
  styleAttr?: string;
  rootAttr?: string;
  portalClassName?: string;
  translationCategory?: string;
};

type ResolvedBuilderShadowConfig = Required<
  Pick<BuilderShadowConfig, 'styleNamespace' | 'styleAttr' | 'rootAttr' | 'portalClassName' | 'translationCategory'>
> &
  BuilderShadowConfig;

const resolveBuilderShadowConfig = (config: BuilderShadowConfig): ResolvedBuilderShadowConfig => {
  const pluginHandle = config.pluginHandle;

  return {
    ...config,
    styleNamespace: config.styleNamespace || pluginHandle,
    styleAttr: config.styleAttr || `data-${pluginHandle}-shadow-style`,
    rootAttr: config.rootAttr || `data-${pluginHandle}-shadow-root`,
    portalClassName: config.portalClassName || `${pluginHandle}-ui`,
    translationCategory: config.translationCategory || pluginHandle,
  };
};

/** Attach a shadow root for a Craft chrome slot and point overlays at that root. */
export const mountBuilderShadowHost = (
  container: HTMLElement,
  config: BuilderShadowConfig,
): { mountNode: HTMLElement; portalContainer: ShadowRoot } => {
  const resolved = resolveBuilderShadowConfig(config);
  const { mountNode, portalContainer, shadowRoot } = mountShadowApp({
    element: container,
    styles: resolved.styleTexts,
    styleAttr: resolved.styleAttr,
    rootAttr: resolved.rootAttr,
  });

  // Chrome slots need their own portal target so overlays stay scoped to that slot.
  configurePluginKitReact({
    portalContainer: shadowRoot,
    shadowRootSelectors: [`[${resolved.rootAttr}]`],
    portalClassName: resolved.portalClassName,
    translationCategory: resolved.translationCategory,
  });

  return { mountNode, portalContainer };
};
