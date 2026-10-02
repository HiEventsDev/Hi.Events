import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { BASE_URL } from './env';

interface DbTarget {
  composeFile: string;
  user: string;
  database: string;
}

const repoPath = (relative: string): string => fileURLToPath(new URL(`../../${relative}`, import.meta.url));

const isDevStack = (): boolean => new URL(BASE_URL).port === '8443';

const defaultTarget = (): DbTarget => isDevStack()
  ? { composeFile: repoPath('docker/development/docker-compose.dev.yml'), user: 'username', database: 'backend' }
  : { composeFile: repoPath('docker/e2e/docker-compose.e2e.yml'), user: 'hievents', database: 'hievents_e2e' };

function dbTarget(): DbTarget {
  const defaults = defaultTarget();
  return {
    composeFile: process.env.E2E_COMPOSE_FILE ?? defaults.composeFile,
    user: process.env.E2E_DB_USER ?? defaults.user,
    database: process.env.E2E_DB_NAME ?? defaults.database,
  };
}

function runSql(sql: string): string {
  const { composeFile, user, database } = dbTarget();
  return execFileSync(
    'docker',
    ['compose', '-f', composeFile, 'exec', '-T', 'pgsql', 'psql', '-U', user, '-d', database, '-v', 'ON_ERROR_STOP=1', '-qtA', '-c', sql],
    { encoding: 'utf8' },
  );
}

const SHORT_ID = /^[A-Za-z0-9_]+$/;

export function expireOrder(shortId: string): void {
  if (!SHORT_ID.test(shortId)) {
    throw new Error(`Refusing to expire order with an unexpected short id: ${shortId}`);
  }
  const updated = runSql(
    `UPDATE orders SET reserved_until = NOW() - INTERVAL '1 hour' WHERE short_id = '${shortId}' RETURNING id`,
  ).trim();
  if (updated === '') {
    throw new Error(`No order found with short id ${shortId}`);
  }
}

const STRIPE_ACCOUNT_ID = /^acct_[A-Za-z0-9]+$/;

export function linkStripeConnectAccount(organizerId: number, stripeAccountId: string): void {
  if (!Number.isInteger(organizerId) || !STRIPE_ACCOUNT_ID.test(stripeAccountId)) {
    throw new Error(`Refusing to link an unexpected Stripe account ${stripeAccountId} to organizer ${organizerId}`);
  }
  runSql(
    `INSERT INTO organizer_stripe_platforms (organizer_id, stripe_connect_account_type, stripe_connect_platform, stripe_account_id, stripe_setup_completed_at, created_at, updated_at)
     SELECT ${organizerId}, 'custom', 'ie', '${stripeAccountId}', NOW(), NOW(), NOW()
     WHERE NOT EXISTS (SELECT 1 FROM organizer_stripe_platforms WHERE organizer_id = ${organizerId} AND deleted_at IS NULL)`,
  );
}

export function stripeReaderIdFor(readerId: number): string {
  if (!Number.isInteger(readerId)) {
    throw new Error(`Refusing to look up an unexpected reader id: ${readerId}`);
  }
  const stripeReaderId = runSql(`SELECT stripe_reader_id FROM stripe_terminal_readers WHERE id = ${readerId}`).trim();
  if (stripeReaderId === '') {
    throw new Error(`No terminal reader found with id ${readerId}`);
  }
  return stripeReaderId;
}
