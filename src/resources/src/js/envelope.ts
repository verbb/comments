import type { FrontendEnvelope } from './types/envelope';
import { CONTRACT_VERSION } from './types/envelope';

export function parseEnvelope(payload: unknown): FrontendEnvelope {
  if (!payload || typeof payload !== 'object') {
    throw new Error('Invalid response envelope.');
  }

  const envelope = payload as FrontendEnvelope;

  if (envelope.contractVersion !== CONTRACT_VERSION) {
    throw new Error(`Unsupported contract version: ${String(envelope.contractVersion)}`);
  }

  return envelope;
}

export function isEnvelopeError(envelope: FrontendEnvelope): boolean {
  return envelope.status === 'error' || envelope.status === 'conflict' || Boolean(envelope.errors?.length);
}

export function envelopeMessage(envelope: FrontendEnvelope): string {
  if (envelope.announcement) {
    return envelope.announcement;
  }

  const first = envelope.errors?.[0];
  return first?.message ?? 'Request failed.';
}
