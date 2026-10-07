import type { ReactNode } from 'react';
import { AppErrorBoundary } from '@verbb/plugin-kit-react/utils';

declare const Craft: {
  t: (category: string, message: string, params?: Record<string, unknown>) => string;
};

type Props = {
  children: ReactNode;
};

/** Supply plugin translations and recovery copy to the shared application boundary. */
export function BuilderErrorBoundary({ children }: Props) {
  return (
    <AppErrorBoundary
      consoleLabel="CP Nav builder crashed:"
      heading={Craft.t('cp-nav', 'Something went wrong')}
      message={Craft.t(
        'cp-nav',
        'The navigation builder failed to load. Please refresh the page or try again.',
      )}
      detailsLabel={Craft.t('cp-nav', 'Show error details')}
      copyLabel={Craft.t('cp-nav', 'Copy error details')}
      copiedLabel={Craft.t('cp-nav', 'Error details copied.')}
      copyErrorLabel={Craft.t(
        'cp-nav',
        'Copy failed. Select the details and copy them manually.',
      )}
      reloadLabel={Craft.t('cp-nav', 'Reload')}
      size="lg"
      className="[--pk-state-panel-min-height:20rem]"
    >
      {children}
    </AppErrorBoundary>
  );
}
