import {expect, test} from '@playwright/test';
import {responsiveImage, sizedImageUrl} from '../../../frontend/src/utilites/imageTransform';

const imagekit = 'https://ik.imagekit.io/hievents/tr:w-1400,q-100/event_cover/poster-AbC12.png';

test('an ImageKit cover offers smaller widths and keeps the configured quality', () => {
  expect(responsiveImage(imagekit, '100vw')).toEqual({
    src: imagekit,
    srcSet: [
      'https://ik.imagekit.io/hievents/tr:w-480,q-100/event_cover/poster-AbC12.png 480w',
      'https://ik.imagekit.io/hievents/tr:w-960,q-100/event_cover/poster-AbC12.png 960w',
      'https://ik.imagekit.io/hievents/tr:w-1400,q-100/event_cover/poster-AbC12.png 1400w',
    ].join(', '),
    sizes: '100vw',
  });
});

test('a small ImageKit image is requested at the width it is shown at', () => {
  expect(sizedImageUrl(imagekit, 96)).toBe('https://ik.imagekit.io/hievents/tr:w-96,q-100/event_cover/poster-AbC12.png');
});

test('self-hosted image URLs are left exactly as they are', () => {
  const selfHosted = 'https://cdn.example.com/hievents-public/event_cover/poster-AbC12.png';
  expect(responsiveImage(selfHosted, '100vw')).toEqual({src: selfHosted});
  expect(sizedImageUrl(selfHosted, 96)).toBe(selfHosted);
});
