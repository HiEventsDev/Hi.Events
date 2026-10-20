let counter = 0;

const token = (): string => {
  counter += 1;
  const worker = process.env.TEST_WORKER_INDEX ?? '0';
  const rand = Math.random().toString(36).slice(2, 8);
  return `${Date.now()}-${worker}-${counter}-${rand}`;
};

export const uniqueEmail = (prefix = 'e2e'): string => `${prefix}+${token()}@hievents.test`;

export const uniqueName = (prefix: string): string => `${prefix} ${token()}`;

export const uniqueCode = (prefix = 'E2E'): string =>
  `${prefix}${token().replace(/-/g, '').toUpperCase()}`.slice(0, 30);

export const uniqueShort = (prefix: string): string =>
  `${prefix} ${Math.random().toString(36).slice(2, 8)}`;
