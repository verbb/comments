import { describe, expect, it } from 'vitest';
import { currentSchemaText, generateSchema, schemaText } from '../scripts/generate-schema.mjs';
import schema from '../schema/data.json';
import { fixture, fixtureManifest, fixtureNames } from './support/fixtures';
import { validate } from './support/schema';

const root = schema as unknown as { $defs: Record<string, unknown> };

describe('the published JSON Schema', () => {
    it('is generated from the current types', () => {
        expect(schemaText(generateSchema())).toBe(currentSchemaText());
    });

    it('describes every response and request', () => {
        for (const name of ['ThreadResponse', 'RepliesResponse', 'CommentResponse', 'ViewerResponse', 'SessionResponse', 'ProtectionResponse', 'StructuredDataResponse', 'EditResponse', 'ErrorResponse',
            'CreateRequest', 'UpdateRequest', 'DeleteRequest', 'VoteRequest', 'ReportRequest', 'FollowRequest', 'ModerateRequest',
            'CreateResponse', 'UpdateResponse', 'DeleteResponse', 'VoteResponse', 'ReportResponse', 'FollowResponse', 'ModerateResponse']) {
            expect(root.$defs, name).toHaveProperty(name);
        }
    });

    it('leaves objects open, so a client never rejects a field added later', () => {
        expect(JSON.stringify(schema)).not.toContain('"additionalProperties":false');
    });
});

describe('the fixtures', () => {
    it('are all listed in the manifest', () => {
        expect(fixtureNames()).toEqual(Object.keys(fixtureManifest).sort());
    });

    it('cover every response definition', () => {
        const covered = new Set(Object.values(fixtureManifest).map((entry) => entry.schema));
        const responses = Object.keys(root.$defs).filter((name) => name.endsWith('Response'));

        expect(responses.filter((name) => !covered.has(name))).toEqual([]);
    });

    it.each(Object.entries(fixtureManifest))('%s matches %o exactly', (name, entry) => {
        expect(validate(root as never, entry.schema, fixture(name))).toEqual([]);
    });

    it('fail validation when a field is misspelt, missing or the wrong kind', () => {
        const thread = fixture('thread.json');
        thread.comments[0].autor = thread.comments[0].author;
        delete thread.comments[0].author;
        thread.comments[1].state = 'deleted';
        thread.page.limit = '20';

        expect(validate(root as never, 'ThreadResponse', thread)).toEqual([
            'ThreadResponse.comments[0].author: missing',
            'ThreadResponse.comments[0].autor: not in the schema',
            'ThreadResponse.comments[1].state: "deleted" isn\'t one of ["approved","placeholder","pending","held","reported","removed","spam"]',
            'ThreadResponse.page.limit: expected number, got string',
        ]);
    });
});
