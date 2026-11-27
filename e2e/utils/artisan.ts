import { execFileSync } from 'node:child_process';
import { composeFile } from './db';

const CLASS_NAME = /^[A-Za-z\\]+$/;

function runArtisan(args: string[]): string {
  return execFileSync(
    'docker',
    ['compose', '-f', composeFile(), 'exec', '-T', 'backend', 'php', 'artisan', ...args],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  );
}

export function runScheduledJob(jobClass: string): void {
  if (!CLASS_NAME.test(jobClass)) {
    throw new Error(`Refusing to run an unexpected scheduled job: ${jobClass}`);
  }
  runArtisan(['schedule:test', `--name=${jobClass}`]);
}

export const runAutomations = (): void => runScheduledJob('HiEvents\\Enterprise\\Crm\\Jobs\\RunAutomationsJob');

export const releaseScheduledCampaigns = (): void => runScheduledJob('HiEvents\\Enterprise\\Crm\\Jobs\\SendScheduledCampaignsJob');

export function evaluatePhp(expression: string): string {
  const lines = runArtisan(['tinker', `--execute=echo ${expression};`]).trim().split('\n');
  return lines[lines.length - 1].trim();
}
