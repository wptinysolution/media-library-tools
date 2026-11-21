/**
 * MCP page client configurations and setup prompts
 * (src/js/Utils/mcpConnect.ts).
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { MCP_CLIENTS, TEST_PROMPT, clientConfig, mcpClient, setupPrompt, type McpCredentials } from '../../src/js/Utils/mcpConnect.ts';

const creds: McpCredentials = {
    username: "o'brien",
    password: 'abcd efgh ijkl mnop qrst uvwx',
    encodedCredentials: Buffer.from("o'brien:abcdefghijklmnopqrstuvwx").toString('base64'),
    serverUrl: 'https://example.test/wp-json/mcp/mcp-adapter-default-server',
    serverName: 'mlt-example-test-mcp-adapter-default-server',
};
const header = `Basic ${creds.encodedCredentials}`;

test('the five clients, Claude Code first', () => {
    assert.deepEqual(MCP_CLIENTS.map((c) => c.id), ['claude-code', 'claude-desktop', 'codex', 'cursor', 'other']);
    assert.equal(mcpClient('nope').id, 'claude-code');
});

test('Claude Code: claude mcp add --transport http with a quoted header', () => {
    const cmd = clientConfig('claude-code', creds);
    assert.ok(cmd.startsWith("claude mcp add --transport http 'mlt-example-test-mcp-adapter-default-server' 'https://example.test/wp-json/mcp/mcp-adapter-default-server' --header "));
    assert.ok(cmd.endsWith(`'Authorization: ${header}'`));
});

test('Claude Desktop: mcp-remote with the header in env', () => {
    const config = JSON.parse(clientConfig('claude-desktop', creds));
    assert.deepEqual(config.mcpServers[creds.serverName], {
        command: 'npx',
        args: ['-y', 'mcp-remote', creds.serverUrl, '--header', 'Authorization:${AUTH_HEADER}'],
        env: { AUTH_HEADER: header },
    });
});

test('Codex: config.toml with url and http_headers', () => {
    assert.equal(
        clientConfig('codex', creds),
        `[mcp_servers."${creds.serverName}"]\nurl = "${creds.serverUrl}"\nhttp_headers = { "Authorization" = "${header}" }\n`,
    );
});

test('Cursor and Other: JSON with url and headers', () => {
    assert.deepEqual(JSON.parse(clientConfig('cursor', creds)).mcpServers[creds.serverName], { url: creds.serverUrl, headers: { Authorization: header } });
    assert.deepEqual(JSON.parse(clientConfig('other', creds)).mcpServers[creds.serverName], { type: 'http', url: creds.serverUrl, headers: { Authorization: header } });
});

test('every setup prompt carries the connection details and its client\'s config', () => {
    for (const client of MCP_CLIENTS) {
        const prompt = setupPrompt(client.id, creds);
        for (const part of [creds.serverUrl, creds.username, creds.password, creds.serverName, clientConfig(client.id, creds)]) {
            assert.ok(prompt.includes(part), `${client.id}: ${part}`);
        }
    }
    assert.match(setupPrompt('claude-desktop', creds), /Node\.js 18/);
    assert.doesNotMatch(setupPrompt('codex', creds), /Node\.js/);
    assert.match(setupPrompt('codex', creds), /~\/\.codex\/config\.toml/);
    assert.match(setupPrompt('cursor', creds), /%USERPROFILE%\\\.cursor\\mcp\.json/);
    assert.match(setupPrompt('claude-code', creds), /claude mcp list/);
});

test('the test prompt names the plugin\'s tools', () => {
    assert.match(TEST_PROMPT, /tsmlt-/);
    assert.match(TEST_PROMPT, /tsmlt-search-media/);
});

test('no fixed site address in the page code', () => {
    for (const file of ['../../src/js/Utils/mcpConnect.ts', '../../src/js/Component/Mcp/McpPage.tsx']) {
        const source = readFileSync(new URL(file, import.meta.url), 'utf8');
        assert.doesNotMatch(source, /plugindev\.local|localhost/, file);
    }
    const page = readFileSync(new URL('../../src/js/Component/Mcp/McpPage.tsx', import.meta.url), 'utf8');
    assert.match(page, /settings\.serverUrl/, 'The URL comes from the server.');
    assert.match(page, /Turn off MCP access/);
    assert.match(page, /Recommended/);
    assert.match(page, /Prefer manual configuration\?/);
    assert.match(page, /I understand that clicking/);
});
