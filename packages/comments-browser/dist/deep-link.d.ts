import { type CommentResponse, type CommentsClient } from '@verbb/comments-core';
export declare function lookUpComment(client: CommentsClient, ref: {
    uid: string;
} | {
    id: number;
}): Promise<CommentResponse | null>;
/** A permalink without its `#comment-N`, which is the owner's own address. */
export declare function permalinkBase(permalink: string | null | undefined): string | null;
