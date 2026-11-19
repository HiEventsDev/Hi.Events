import { execFileSync } from 'node:child_process';
import { composeFile } from './db';

const THROTTLE_NAME = /^[a-z-]+$/;

export function liftThrottle(name: string): void {
  if (!THROTTLE_NAME.test(name)) {
    throw new Error(`Refusing to lift an unexpected throttle: ${name}`);
  }
  const forget = [0, 1]
    .map((database) => `for key in $(redis-cli -n ${database} --scan --pattern '*${name}*'); do redis-cli -n ${database} del "$key" > /dev/null; done`)
    .join('; ');
  execFileSync('docker', ['compose', '-f', composeFile(), 'exec', '-T', 'redis', 'sh', '-c', forget], { stdio: ['ignore', 'pipe', 'pipe'] });
}
