export const CONTRACT_VERSION = 1;

export type FragmentType =
  | 'conversationHeader'
  | 'rootList'
  | 'comment'
  | 'replies'
  | 'thread'
  | 'reportDialog'
  | 'composer'
  | 'viewerControls';

export type FrontendFragment = {
  type: FragmentType;
  mode: string;
  resourceUid: string;
  html: string;
};

export type EnvelopeError = {
  category: string;
  code: string;
  message: string;
  fieldErrors?: Record<string, string[]>;
  httpStatus?: number;
  recoverable?: boolean;
};

export type FrontendEnvelope = {
  contractVersion: number;
  operationId?: string;
  status: 'ok' | 'error' | 'conflict';
  resource?: string;
  workflowVersion?: string;
  replayed?: boolean;
  secondaryWorkPending?: boolean;
  fragments?: FrontendFragment[];
  state?: Record<string, unknown>;
  errors?: EnvelopeError[];
  announcement?: string;
  rootsNext?: string | null;
  repliesNext?: string | null;
};
