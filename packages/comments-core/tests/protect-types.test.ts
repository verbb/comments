import type { BrowserManifest, ChallengeManifest, Evidence } from '@verbb/protect';
import { describe, expect, it } from 'vitest';
import type { ProtectionChallengeData, ProtectionEvidenceData, ProtectionManifestData } from '../src/index';

// Core writes Protect's JSON types out itself, so its declarations need neither Protect's types
// nor the DOM's. These assignments stop compiling (in `npm run typecheck`) if the two drift apart.
type Same<A, B> = [A] extends [B] ? ([B] extends [A] ? true : false) : false;
const manifest: Same<ProtectionManifestData, BrowserManifest> = true;
const challenge: Same<ProtectionChallengeData, ChallengeManifest> = true;
const evidence: Same<ProtectionEvidenceData, Evidence> = true;

describe('Protect’s types', () => {
    it('match core’s own copies', () => {
        expect([manifest, challenge, evidence]).toEqual([true, true, true]);
    });
});
