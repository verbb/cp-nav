export type CraftElementSelectorElement = {
  id: number;
  label?: string;
  title?: string;
  url?: string;
};

export type CpNavCraft = {
  t: (category: string, message: string, params?: Record<string, unknown>) => string;
  getUrl: (path: string, params?: Record<string, unknown> | string | null) => string;
  cp: {
    displayNotice: (message: string) => void;
    displayError: (message?: string) => void;
  };
  sendActionRequest: (
    method: 'GET' | 'POST',
    action: string,
    options?: {
      data?: Record<string, unknown>;
      params?: Record<string, unknown>;
      signal?: AbortSignal;
    },
  ) => Promise<{ data: Record<string, unknown> }>;
  createElementSelectorModal: (
    elementType: string,
    settings: {
      multiSelect?: boolean;
      sources?: string[];
      criteria?: Record<string, unknown>;
      storageKey?: string;
      onSelect?: (elements: CraftElementSelectorElement[]) => void;
      onCancel?: () => void;
      /** Garnish.Modal — fires on any dismiss (Cancel, shade, Esc, after Select). */
      onHide?: () => void;
    },
  ) => unknown;
};

export function getCraft(): CpNavCraft {
  return (window as unknown as { Craft: CpNavCraft }).Craft;
}
