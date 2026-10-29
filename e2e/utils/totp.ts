import { createHmac } from 'node:crypto';

const STEP_SECONDS = 30;
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const lastUsedStep = new Map<string, number>();

const base32Decode = (input: string): Buffer => {
  const clean = input.replace(/[\s=]/g, '').toUpperCase();
  let bits = '';
  for (const character of clean) {
    const index = BASE32_ALPHABET.indexOf(character);
    if (index === -1) throw new Error(`Invalid base32 character "${character}"`);
    bits += index.toString(2).padStart(5, '0');
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
};

const currentStep = (): number => Math.floor(Date.now() / 1000 / STEP_SECONDS);

const codeForStep = (secret: string, step: number): string => {
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(step));
  const digest = createHmac('sha1', base32Decode(secret)).update(counter).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const value = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;
  return value.toString().padStart(6, '0');
};

export async function nextTotpCode(secret: string): Promise<string> {
  const key = secret.replace(/\s/g, '');
  let step = Math.max(currentStep(), (lastUsedStep.get(key) ?? 0) + 1);

  while (step > currentStep() + 1) {
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }

  lastUsedStep.set(key, step);
  return codeForStep(key, step);
}
