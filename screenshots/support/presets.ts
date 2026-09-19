import type { ScreenshotStep } from '@verbb/craft-screenshots/types';

export function createExactFrameStep(selector: string, width: number, height: number, scale = 1): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                document.getElementById('cp-nav-screenshot-frame')?.remove();
                const source = document.querySelector(${JSON.stringify(selector)});
                if (!source) throw new Error('CP Nav screenshot source not found: ' + ${JSON.stringify(selector)});
                const frame = document.createElement('div');
                frame.id = 'cp-nav-screenshot-frame';
                frame.style.cssText = [
                    'position:fixed', 'left:0', 'top:0', 'width:${width}px', 'height:${height}px',
                    'overflow:hidden', 'background:#ffffff', 'z-index:2147483646', 'box-sizing:border-box'
                ].join(';');
                const clone = source.cloneNode(true);
                clone.style.transform = 'scale(${scale})';
                clone.style.transformOrigin = 'top left';
                clone.style.width = (100 / ${scale}) + '%';
                clone.style.minHeight = (100 / ${scale}) + '%';
                clone.style.margin = '0';
                frame.appendChild(clone);
                document.body.appendChild(frame);
                document.documentElement.style.background = '#fff';
                document.body.style.margin = '0';
                document.body.style.overflow = 'hidden';
            })();
        `,
    };
}
