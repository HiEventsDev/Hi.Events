import {expect, test} from '@playwright/test';
import {loggableError} from '../../../frontend/src/ssr/loggableError.js';

test('a failed backend call is logged without the headers it was sent with', () => {
  const error = Object.assign(new Error('Request failed with status code 500'), {
    isAxiosError: true,
    code: 'ERR_BAD_RESPONSE',
    config: {method: 'get', url: '/public/events/1', headers: {'X-Hi-Ssr-Key': 'ssr-secret', Authorization: 'Bearer visitor-jwt'}},
    request: {_header: 'GET /public/events/1\r\nX-Hi-Ssr-Key: ssr-secret\r\nAuthorization: Bearer visitor-jwt\r\n'},
    response: {status: 500, headers: {}, config: {headers: {'X-Hi-Ssr-Key': 'ssr-secret'}}},
  });

  const logged = JSON.stringify(loggableError(error));

  expect(logged).not.toContain('ssr-secret');
  expect(logged).not.toContain('visitor-jwt');
  expect(loggableError(error)).toEqual({
    message: 'Request failed with status code 500',
    code: 'ERR_BAD_RESPONSE',
    status: 500,
    method: 'get',
    url: '/public/events/1',
  });
});

test('other errors are logged unchanged', () => {
  const error = new Error('Render failed');

  expect(loggableError(error)).toBe(error);
});
