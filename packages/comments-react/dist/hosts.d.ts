import type { ComponentHost, MountOptions } from '@verbb/comments-browser';
import { type ReactNode } from 'react';
import { type CommentsFieldOverrides, type CommentsPartOverrides } from './overrides.js';
/**
 * The thread's component hosts, kept for React. The renderer draws an empty host element where a
 * component override goes and reports every host after each draw; each one becomes a portal, so the
 * component stays in the app's tree with its context, state and router, and follows its host when
 * the renderer redraws or removes it.
 */
export interface HostRegistry {
    /** Takes the renderer's latest list, as `onHosts` gives it. */
    set(hosts: readonly ComponentHost[]): void;
    clear(): void;
    /** A short ID for an override's identity, so swapping one component for another counts as a change. */
    idOf(value: unknown): string;
    /** The portals, one per host. */
    useHosts(): ReactNode;
}
export declare function createHostRegistry(): HostRegistry;
type RendererOverrides = Pick<MountOptions, 'parts' | 'fields'>;
/**
 * The thread's overrides as the renderer takes them: a component becomes `{ component }`, which the
 * renderer draws as a host, a `templatePart()` function goes as the function itself, and a custom
 * element's name as it is.
 */
export declare function rendererOverrides(parts: CommentsPartOverrides, fields: CommentsFieldOverrides): RendererOverrides;
/** A value that names the overrides' identities, so a different component or function counts as a change. */
export declare function overridesKey(parts: CommentsPartOverrides, fields: CommentsFieldOverrides, registry: HostRegistry): string;
export {};
