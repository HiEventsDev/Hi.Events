import type { Frame, Page } from '@playwright/test';

export interface ImageFitProblem {
  container: string;
  image: [number, number];
  box: [number, number];
}

export interface ImageFitReport {
  images: number;
  horizontalOverflow: number;
  problems: ImageFitProblem[];
}

export async function stressImageFit(target: Page | Frame): Promise<ImageFitReport> {
  return target.evaluate(async () => {
    const shapes: [number, number][] = [[800, 800], [300, 1200], [2400, 200], [40, 20], [1200, 800]];
    const draw = ([width, height]: [number, number]) => {
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext('2d')!;
      context.fillStyle = '#e11d48';
      context.fillRect(0, 0, width, height);
      return canvas.toDataURL();
    };

    const images = [...document.querySelectorAll('img')].filter(image => image.offsetParent !== null);
    images.forEach((image, index) => {
      image.loading = 'eager';
      image.removeAttribute('srcset');
      image.src = draw(shapes[index % shapes.length]);
    });
    await Promise.all(images.map(image => image.decode().catch(() => undefined)));
    await new Promise(resolve => setTimeout(resolve, 500));
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    const clipsNearby = (image: HTMLImageElement) => {
      for (let node = image.parentElement, depth = 0; node && depth < 2; node = node.parentElement, depth++) {
        const style = getComputedStyle(node);
        if (style.overflowX !== 'visible' || style.overflowY !== 'visible' || style.clipPath !== 'none') {
          return true;
        }
      }
      return false;
    };

    const problems = images.flatMap(image => {
      const rect = image.getBoundingClientRect();
      const box = image.parentElement!.getBoundingClientRect();
      const spills = rect.left < box.left - 1.5 || rect.right > box.right + 1.5 || rect.top < box.top - 1.5 || rect.bottom > box.bottom + 1.5;
      const cropped = spills && clipsNearby(image) && getComputedStyle(image).objectFit !== 'cover';
      return (spills && !clipsNearby(image)) || cropped
        ? [{
          container: image.parentElement!.className.toString().slice(0, 60) || image.parentElement!.tagName,
          image: [Math.round(rect.width), Math.round(rect.height)] as [number, number],
          box: [Math.round(box.width), Math.round(box.height)] as [number, number],
        }]
        : [];
    });

    return {
      images: images.length,
      horizontalOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      problems,
    };
  });
}
