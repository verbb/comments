import type { FrontendEnvelope } from './types/envelope';
import { parseEnvelope } from './envelope';

const DEFAULT_TIMEOUT_MS = 30000;

export type RequestOptions = {
  method?: string;
  body?: FormData | URLSearchParams | string;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeoutMs?: number;
};

function csrfFromForm(root: ParentNode): { name: string; value: string } | null {
  const parameter = root instanceof HTMLElement ? root.dataset.commentsCsrfParam : undefined;
  if (!parameter) return null;
  const csrf = Array.from(root.querySelectorAll<HTMLInputElement>('input[type="hidden"][name]')).find((input) => input.name === parameter);
  return csrf?.value ? { name: csrf.name, value: csrf.value } : null;
}

export async function requestEnvelope(url: string, root: ParentNode, options: RequestOptions = {}): Promise<FrontendEnvelope> {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), options.timeoutMs ?? DEFAULT_TIMEOUT_MS);

  const signal = options.signal
    ? AbortSignal.any([options.signal, controller.signal])
    : controller.signal;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers ?? {}),
  };

  let body = options.body;

  if (body instanceof FormData) {
    body.delete('action');
    const csrf = csrfFromForm(root);
    if (csrf && !body.has(csrf.name)) {
      body.set(csrf.name, csrf.value);
    }
  }

  try {
    const response = await fetch(url, {
      method: options.method ?? 'GET',
      body,
      headers,
      credentials: 'same-origin',
      signal,
    });

    let envelope: FrontendEnvelope;

    // A server error page isn't something to show anyone, so it becomes a sentence they can act on.
    try {
      envelope = parseEnvelope(await response.json());
    } catch (error) {
      if (signal.aborted) {
        throw error;
      }

      throw new Error((root as HTMLElement).dataset?.commentsFailedMessage ?? 'Something went wrong. Please try again.');
    }

    if (!response.ok && envelope.status === 'ok') {
      envelope.status = 'error';
      envelope.errors = envelope.errors ?? [{
        category: 'http',
        code: 'http_error',
        message: `HTTP ${response.status}`,
        httpStatus: response.status,
        recoverable: response.status >= 500,
      }];
    }

    return envelope;
  } finally {
    window.clearTimeout(timeout);
  }
}
