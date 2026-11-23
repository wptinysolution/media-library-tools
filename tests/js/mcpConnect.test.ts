/**
 * MCP page client configurations and setup prompts
 * (src/js/Utils/mcpConnect.ts).
 *
 *     node --test tests/js/
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { MCP_CLIENTS, MCP_DISABLED_MESSAGE, TEST_PROMPT, adapterBadge, clientConfig, connectionState, mcpClient, setupPrompt, type McpConnectionSettings, type McpCredentials } from '../../src/js/Utils/mcpConnect.ts';

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

test('every setup prompt carries the connection details and its client\'s config, with the Authorization header', () => {
    for (const client of MCP_CLIENTS) {
        const prompt = setupPrompt(client.id, creds);
        for (const part of [creds.serverUrl, creds.serverName, clientConfig(client.id, creds), header]) {
            assert.ok(prompt.includes(part), `${client.id}: ${part}`);
        }
    }
});

test('no separate username or application password in the prompt', () => {
    for (const client of MCP_CLIENTS) {
        const prompt = setupPrompt(client.id, creds);
        assert.ok(!prompt.includes(creds.password), `${client.id}: no plain password`);
        assert.ok(!prompt.includes(creds.password.replace(/ /g, '')), `${client.id}: no unchunked password`);
        assert.doesNotMatch(prompt, /Username:|Application password:/i, client.id);
        assert.ok(!prompt.split('\n').some((line) => line.trim() === `- ${creds.username}` || line.includes(`: ${creds.username}`)), `${client.id}: no username line`);
    }
});

test('each client has its own, complete configuration', () => {
    const configs = MCP_CLIENTS.map((client) => clientConfig(client.id, creds));
    assert.equal(new Set(configs).size, MCP_CLIENTS.length);
    for (const config of configs) {
        assert.ok(config.includes(header) && config.includes(creds.serverUrl));
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
    assert.doesNotMatch(page, /https?:\/\/[a-z]/i, 'No URL in the page code; URLs come from the server.');
    assert.match(page, /Turn off MCP access/);
    assert.match(page, /title="Manual configuration"/);
    assert.match(page, /I understand that clicking/);
});

const ready: McpConnectionSettings = {
    enabled: true,
    adapterStatus: 'active',
    abilitiesSupported: true,
    defaultServerSupported: true,
    canCreatePassword: true,
    passwordWarning: '',
};
const page = (): string => readFileSync(new URL('../../src/js/Component/Mcp/McpPage.tsx', import.meta.url), 'utf8');

test('access off: only the switch and the message', () => {
    assert.equal(MCP_DISABLED_MESSAGE, 'MCP access is currently disabled. Turn off this setting to enable MCP connections.');
    assert.deepEqual(connectionState({ ...ready, enabled: false }, true, false), { showConnection: false, defaultAvailable: true, blocker: '', canGenerate: false });
    assert.equal(connectionState({ ...ready, enabled: true }, false, false).showConnection, true, 'Back when access is on again.');
});

test('Generate needs consent, and is off while a request runs', () => {
    assert.equal(connectionState(ready, false, false).canGenerate, false);
    assert.equal(connectionState(ready, true, false).canGenerate, true);
    assert.equal(connectionState(ready, true, true).canGenerate, false);
});

test('site prerequisites block Generate with a reason, but never the checkbox', () => {
    const adapter = connectionState({ ...ready, adapterStatus: 'missing' }, true, false);
    assert.equal(adapter.canGenerate, false);
    assert.match(adapter.blocker, /MCP Adapter/);
    const passwords = connectionState({ ...ready, canCreatePassword: false, passwordWarning: 'Application Passwords are turned off for your account.' }, true, false);
    assert.equal(passwords.canGenerate, false);
    assert.match(passwords.blocker, /turned off for your account/);
    assert.equal(connectionState(ready, true, false).blocker, '');
});

test('the consent checkbox is a plain controlled checkbox with its own label', () => {
    const source = page();
    const at = source.indexOf('id="tsmlt-mcp-consent"');
    const input = source.slice(source.lastIndexOf('<input', at), source.indexOf('/>', at) + 2);
    assert.match(input, /type="checkbox"/);
    assert.match(input, /checked=\{consent\}/);
    assert.match(input, /onChange=\{\(event\) => setConsent\(event\.target\.checked\)\}/);
    assert.doesNotMatch(input, /disabled=/, 'Nothing else can stop the box from being checked.');
    assert.match(source, /<label htmlFor="tsmlt-mcp-consent"/, 'Clicking the label toggles it.');
    assert.match(source, /disabled=\{!state\.canGenerate\}/, 'Generate waits for consent.');
    assert.match(source, /const action = 'Generate Prompt';/);
    const generate = source.slice(source.indexOf('const generate'), source.indexOf('const selectClient'));
    assert.match(generate, /setConsent\(false\)/, 'Consent resets after a password was created.');
    const select = source.slice(source.indexOf('const selectClient'), source.indexOf('return (', source.indexOf('const selectClient')));
    assert.match(select, /setConsent\(false\)/, 'Each client asks again.');
});

test('while access is off nothing but the switch and the message is rendered', () => {
    const source = page();
    const branch = source.indexOf('{!state.showConnection ? (');
    const connection = source.indexOf(') : (', branch);
    assert.ok(branch > 0 && connection > branch);
    assert.ok(source.indexOf('{MCP_DISABLED_MESSAGE}') > branch && source.indexOf('{MCP_DISABLED_MESSAGE}') < connection);
    for (const part of ['settings.dedicatedUrl', 'MCP_CLIENTS.map', 'TEST_PROMPT}', 'title="Manual configuration"', 'tsmlt-mcp-consent', 'id="tsmlt-mcp-advanced-title"', 'Choose your client', 'CopyToClipboard text={TEST_PROMPT}']) {
        assert.ok(source.indexOf(part) > connection, `${part} only when access is on`);
    }
    assert.ok(source.indexOf('Turn off MCP access') < branch, 'The switch is always there.');
});

test('the dedicated endpoint is the alternative, under Advanced', () => {
    const source = page();
    assert.doesNotMatch(source, /Recommended connection/, 'No separate recommended-connection section.');
    const advanced = source.indexOf('Advanced configuration');
    const disclosure = source.indexOf('id="tsmlt-mcp-dedicated"');
    assert.ok(advanced > 0 && disclosure > advanced, 'Inside Advanced configuration.');
    assert.match(source.slice(disclosure, disclosure + 400), /title="Dedicated Media Library Tools MCP server"/);
    assert.match(source.slice(disclosure, disclosure + 400), /open=\{dedicated\}/);
});

test('while the default server is unavailable Generate is blocked with the reason', () => {
    assert.equal(connectionState(ready, false, false).defaultAvailable, true);
    for (const missing of [{ adapterStatus: 'missing' }, { adapterStatus: 'inactive' }, { defaultServerSupported: false }, { abilitiesSupported: false }]) {
        const state = connectionState({ ...ready, ...missing }, true, false);
        assert.equal(state.defaultAvailable, false, JSON.stringify(missing));
        assert.equal(state.canGenerate, false);
        assert.match(state.blocker, /installed and active/);
    }
});

test('manual configuration expands and collapses, and shows the chosen client\'s whole configuration', () => {
    const source = page();
    // The shared disclosure: a button that says whether it is open and which region it controls.
    const disclosure = source.slice(source.indexOf('function Disclosure'), source.indexOf('function Spinner'));
    assert.match(disclosure, /aria-expanded=\{open\}/);
    assert.match(disclosure, /aria-controls=\{id\}/);
    assert.match(disclosure, /onClick=\{onToggle\}/);
    assert.match(disclosure, /\{open && \(\s*<div id=\{id\} role="region"/);
    const manual = source.slice(source.indexOf('id="tsmlt-mcp-manual"'), source.indexOf('</Disclosure>', source.indexOf('id="tsmlt-mcp-manual"')));
    assert.match(manual, /open=\{manual\}/);
    assert.match(manual, /onToggle=\{\(\) => setManual\(!manual\)\}/);
    assert.match(manual, /text=\{creds \? clientConfig\(client, creds\) : ''\}/, 'The selected client\'s full configuration.');
    assert.match(manual, /copyLabel="Copy configuration"/);
    assert.match(manual, /\bscroll\b/, 'Long lines scroll instead of breaking the layout.');
    const box = source.slice(source.indexOf('function CodeBox'), source.indexOf('function UrlBox'));
    assert.match(box, /<CopyToClipboard text=\{text\}/, 'Copies the whole text.');
    assert.match(box, /overflow-x-auto/);
});

test('client tabs follow the tabs pattern, with arrow-key navigation', () => {
    const source = page();
    assert.match(source, /role="tablist" aria-label="MCP client"/);
    assert.match(source, /aria-selected=\{client === item\.id\}/);
    assert.match(source, /tabIndex=\{client === item\.id \? 0 : -1\}/);
    assert.match(source, /onKeyDown=\{\(event\) => onTabKey\(event, index\)\}/);
    assert.match(source, /ArrowRight:.*ArrowLeft:.*Home: 0, End: last/);
    assert.match(source, /role="tabpanel"/);
});

test('the access switch is a labelled switch, and status is shown', () => {
    const source = page();
    const at = source.indexOf('id="tsmlt-mcp-access-off"');
    const input = source.slice(source.lastIndexOf('<input', at), source.indexOf('/>', at));
    assert.match(input, /role="switch"/);
    assert.match(input, /checked=\{!settings\.enabled\}/);
    assert.match(source, /<label htmlFor="tsmlt-mcp-access-off"/);
    assert.match(source, /Enabled<\/Badge>/);
    assert.match(source, /Disabled<\/Badge>/);
});

test('adapter status label', () => {
    assert.deepEqual(adapterBadge(ready), { tone: 'green', label: 'Adapter active' });
    assert.equal(adapterBadge({ ...ready, adapterStatus: 'missing' }).label, 'Adapter not installed');
    assert.equal(adapterBadge({ ...ready, adapterStatus: 'inactive' }).label, 'Adapter inactive');
    assert.equal(adapterBadge({ ...ready, defaultServerSupported: false }).label, 'Adapter update needed');
    assert.equal(adapterBadge({ ...ready, abilitiesSupported: false }).label, 'Needs WordPress 6.9+');
});

test('the default server URL is copyable only while it is available', () => {
    const source = page();
    const branch = source.indexOf('{state.defaultAvailable ? (');
    const otherwise = source.indexOf(') : (', branch);
    const url = source.indexOf('<UrlBox url={settings.serverUrl} />');
    assert.ok(branch > 0 && url > branch && url < otherwise);
    assert.match(source.slice(otherwise, otherwise + 400), /installed and active/);
    assert.doesNotMatch(source, /Recommended connection/);
});

test('the dedicated endpoint is always listed under Advanced, whatever the adapter state', () => {
    const source = page();
    const advanced = source.indexOf('Advanced: dedicated Media Library Tools MCP server');
    assert.ok(source.indexOf('<UrlBox url={settings.dedicatedUrl} />') > advanced);
    assert.doesNotMatch(source.slice(advanced, advanced + 1500), /defaultAvailable/, 'Not tied to the default server.');
});
